<?php

namespace WSSlots;

class ArticleParser {
	/**
	 * @var string
	 */
	protected $article  = '';

	/**
	 * @var string
	 */
	protected $template = '';

	/**
	 * @var string
	 */
	protected $argument = '';

	/**
	 * @var string
	 */
	protected $templateName = '';

	/**
	 * @var string
	 */
	private $anonymousArgumentPointer = '';

	/**
	 * @var array
	 */
	private $templateArguments = [];

	/**
	 * @var array
	 */
	private $templates = [];

	/**
	 * @var array
	 */
	private $parameters = [];

	/**
	 * This function parses an article. It looks for all the templates on the article, then returns an array containing the parameters of those templates. E.g.:
	 * [ "Template1" => [ "Param1" => "Value1", "Param2" => "Value2" ], "Template2" => [ "Param1" => "Value1", "Param2" => "Value2" ] ]
	 *
	 * @param $article
	 * @return array
	 */
	public function parseArticle($article) {
		$this->article = $article;

		$this->findTemplates();
		$this->parseTemplates();

		return $this->templateArguments;
	}

	/**
	 * Parses the templates in $this->templates.
	 */
	protected function parseTemplates() {
		$this->templateArguments = [];

		foreach($this->templates as $template) {
			$this->template = $template;
			$this->parseTemplate();
		}

	}

	/**
	 * Parses a single template. It first removes the accolades from the template, then splits the template by arguments.
	 */
	protected function parseTemplate() {
		$template = substr($this->template, 2);
		$template = substr($template, 0, -2);

		$arguments = $this->tokenizeTemplate($template);

		$this->anonymousArgumentPointer = 1;
		$this->templateName = trim(array_shift($arguments));

		foreach($arguments as $argument) {
			$this->argument = $argument;
			$this->parseArgument();
		}
	}

	/**
	 * Parses a template and splits it based on arguments, while respecting (nesting) multiple-instance templates.
	 *
	 * @param $template
	 * @return array
	 */
	protected function tokenizeTemplate($template) {
		$template = str_split($template);
		$arguments = [];

		$buffer = '';
		$nesting_depth = 0;
		foreach($template as $index => $char) {
			if ($char === "{" && $template[$index + 1] === "{") { // Check if a template starts
				$nesting_depth++;
			} else if ($nesting_depth > 0 && $char === "}" && $template[$index + 1] === "}") {
				// Check if a template ends
				$nesting_depth--;
			} else if ($nesting_depth === 0 && $char === "|") {
				$arguments[] = $buffer;
				$buffer = '';

				continue;
			}

			$buffer .= $char;
		}

		$arguments[] = $buffer;

		return $arguments;
	}

	/**
	 * Parses a template argument and adds it to $this->templateArguments.
	 */
	protected function parseArgument() {
		$argument_parts = explode("=", $this->argument, 2);

		if(substr($this->argument, -1) === "=") {
			trim($argument_parts[0], "=");
			$argument_parts[1] = ""; // Empty value named argument
		}

		if(count($argument_parts) === 1) {
			// Anonymous argument
			$this->templateArguments[$this->templateName][strval($this->anonymousArgumentPointer)] = trim( $argument_parts[0] );
			$this->anonymousArgumentPointer++;
		} else {
			// Named argument
			$argument_name = trim( array_shift($argument_parts) );
			$argument_value = trim( implode("=", $argument_parts) );

			if($this->parameters && !in_array($argument_name, $this->parameters)) {
				return;
			}

			$this->templateArguments[$this->templateName][$argument_name] = $argument_value;
		}
	}

	/**
	 * Finds all the templates on a page. This function takes nested templates into account.
	 */
	protected function findTemplates() {
		$this->templates = [];
		$this->template  = '';

		$open_brackets = 0;

		if (version_compare(PHP_VERSION, "7.4") >= 0) {
			$characters = mb_str_split( $this->article );
		} else {
			$characters = str_split( $this->article );
		}

		for ($idx = 0; $idx < count( $characters ); $idx++) {
			$current_character = $characters[$idx];
			$next_character = isset($characters[$idx + 1]) ? $characters[$idx + 1] : "\0";

			if ($current_character === "{" && $next_character === "{") {
				$open_brackets++;
				$idx++;

				// Add the "{" we skipped
				$this->template .= $current_character;
			} else if ($current_character === "}" && $next_character === "}") {
				$open_brackets--;
				$idx++;

				// Add the "}" we skipped
				$this->template .= $current_character;
			}

			if ($open_brackets > 0) {
				$this->template .= $current_character;
			}

			if ($open_brackets === 0 && strlen($this->template) > 0) {
				$this->template .= $current_character;
				$this->flushTemplate();
			}
		}
	}

	/**
	 * Check if this is a valid template.
	 *
	 * @return bool
	 */
	private function isValidTemplate() {
		$template = $this->template;

		if($template[0] !== '{' || $template[1] !== '{') {
			return false;
		}

		if($template[strlen($template) - 1] !== '}' || $template[strlen($template) - 2] !== '}') {
			return false;
		}

		if(isset($this->template[2]) && $this->template[2] === "#") {
			return false;
		}

		return true;
	}

	/**
	 * Adds the template to the $this->templates array and clears the parameter.
	 */
	private function flushTemplate() {
		if($this->isValidTemplate()) {
			array_push($this->templates, $this->template);
		}

		$this->template = '';
	}
}