--[[
	Registers methods that can be accessed through the Scribunto extension

	@since 0.1

	@licence GNU GPL v2+
	@author mwjames
]]

-- Variable instantiation
local slots = {}
local php

function slots.setupInterface()
    -- Interface setup
    slots.setupInterface = nil
    php = mw_interface
    mw_interface = nil

    -- Register library within the "mw.smw" namespace
    mw = mw or {}
    mw.slots = slots

    package.loaded['mw.slots'] = slots
end

-- slotTemplates
function slots.slotTemplates( slotName, pageName )
    if not type( slotName ) == 'string' or not type( pageName ) == 'string' or not type( pageName ) == 'nil' then
        error( 'Invalid parameter type supplied to slots.slotTemplates()' )
    end

    return php.slotTemplates( slotName, pageName )
end

return slots