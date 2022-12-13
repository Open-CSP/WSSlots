-- Variable instantiation
local slots = {}
local php

function slots.setupInterface()
    -- Interface setup
    slots.setupInterface = nil
    php = mw_interface
    mw_interface = nil

    -- Register library within the "mw.slots" namespace
    mw = mw or {}
    mw.slots = slots

    package.loaded['mw.slots'] = slots
end

-- slotContent
function slots.slotContent( slotName, pageName )
    if not type( slotName ) == 'string' or not type( pageName ) == 'string' or not type( pageName ) == 'nil' then
        error( 'Invalid parameter type supplied to mw.slots.slotContent()' )
    end

    return php.slotContent( slotName, pageName )
end

-- slotTemplates
function slots.slotTemplates( slotName, pageName )
    if not type( slotName ) == 'string' or not type( pageName ) == 'string' or not type( pageName ) == 'nil' then
        error( 'Invalid parameter type supplied to mw.slots.slotTemplates()' )
    end

    return php.slotTemplates( slotName, pageName )
end

return slots
