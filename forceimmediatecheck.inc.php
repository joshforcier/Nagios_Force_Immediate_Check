<?php

// Force Immediate Check Component

require_once(dirname(__FILE__) . '/../componenthelper.inc.php');

$forceimmediatecheck_component_name = "forceimmediatecheck";
forceimmediatecheck_component_init();

////////////////////////////////////////////////////////////////////////
// COMPONENT INIT FUNCTIONS
////////////////////////////////////////////////////////////////////////

function forceimmediatecheck_component_init()
{
    global $forceimmediatecheck_component_name;
    $versionok = forceimmediatecheck_component_checkversion();
    $desc = _("This component allows administrators to force an immediate check of hosts and services.");

    if (!$versionok) {
        $desc = "<b>" . _("Error: This component requires Nagios XI 2009R1.2B or later.") . "</b>";
    }

    $args = array(
        COMPONENT_NAME => $forceimmediatecheck_component_name,
        COMPONENT_VERSION => '1.0.0',
        COMPONENT_DATE => '10/31/2018',
        COMPONENT_AUTHOR => "Josh Forcier",
        COMPONENT_DESCRIPTION => $desc,
        COMPONENT_TITLE => _("Force Immediate Check"),
        COMPONENT_REQUIRES_VERSION => 500
    );

    // Register this component with XI
    register_component($forceimmediatecheck_component_name, $args);

    // Register the addmenu function
    if ($versionok) {
        register_callback(CALLBACK_MENUS_INITIALIZED, 'forceimmediatecheck_component_addmenu');
    }
}

function forceimmediatecheck_component_checkversion()
{
    if (!function_exists('get_product_release'))
        return false;
    if (get_product_release() < 114)
        return false;
    return true;
}

function forceimmediatecheck_component_addmenu($arg = null)
{
    if (is_readonly_user(0)) {
        return;
    }

    global $forceimmediatecheck_component_name;
    $urlbase = get_component_url_base($forceimmediatecheck_component_name);

    $mi = find_menu_item(MENU_HOME, "menu-home-acknowledgements", "id");
    if ($mi == null) {
        return;
    }

    $order = grab_array_var($mi, "order", "");
    if ($order == "") {
        return;
    }

    $neworder = $order + 0.1;

    add_menu_item(MENU_HOME, array(
        "type" => "link",
        "title" => _("Force Immediate Check"),
        "id" => "menu-home-forceimmediatecheck",
        "order" => $neworder,
        "opts" => array(
            "href" => $urlbase . "/index.php"
        )
    ));
}
