<?php

// Force Immediate Check Component

require_once(dirname(__FILE__) . '/../../common.inc.php');

// Initialization stuff
pre_init();
init_session();

// Grab GET or POST variables and do prereq/auth checks
grab_request_vars();
check_prereqs();
check_authentication(false);

$title = _("Force Immediate Check");
do_page_start(array("page_title" => $title), true);

$hostgroup = grab_request_var("hostgroup", "");
$servicegroup = grab_request_var("servicegroup", "");
$select_state = grab_request_var("selectstate", "");
$host = grab_request_var("host", "");
$host_id = "";
$service_id = "";

/////////////////////////////////////
// Limit by host/service group HTML
/////////////////////////////////////
?>
<form method="get">
    <div class="well report-options form-inline">
        <div class="input-group" style="margin-right: 10px;">
            <label class="input-group-addon"><?php echo _("Limit To"); ?></label>                    
            <select name="hostgroup" id="hostgroupList" style="width: 150px;" class="form-control">
                <option value=""><?php echo _("Hostgroup"); ?>:</option>
                <?php
                $args = array('orderby' => 'hostgroup_name:a');
                $oxml = get_xml_hostgroup_objects($args);
                if ($oxml) {
                    foreach ($oxml->hostgroup as $hg) {
                        $name = strval($hg->hostgroup_name);
                        echo "<option value='" . $name . "' " . is_selected($hostgroup, $name) . ">$name</option>";
                    }
                }
                ?>
            </select>
            <select name="servicegroup" id="servicegroupList" style="width: 150px;" class="form-control">
                <option value=""><?php echo _("Servicegroup"); ?>:</option>
                <?php
                $args = array('orderby' => 'servicegroup_name:a');
                $oxml = get_xml_servicegroup_objects($args);
                if ($oxml) {
                    foreach ($oxml->servicegroup as $sg) {
                        $name = strval($sg->servicegroup_name);
                        echo "<option value='" . $name . "' " . is_selected($servicegroup, $name) . ">$name</option>";
                    }
                }
                ?>
            </select>
            <select name="selectstate" id="selectstate" style="width: 150px;" class="form-control">
                <?php
                echo "<option value='" . 'Show Problems' . "' " . is_selected($select_state, 'Show Problems') . ">Show Problems</option>";
                echo "<option value='" . 'Show All' . "' " . is_selected($select_state, 'Show All') . ">Show All</option>";
                ?>
            </select>
            <input type="submit" style="margin-left: 10px;" id="runButton" class='btn btn-sm btn-primary' name='runButton' value="<?php echo _("Run"); ?>">
        </div>
    </div>
</form>
<?php

/////////////////////////////////
// Filtering by group/state
/////////////////////////////////

$select_state = grab_request_var("selectstate", "");
$hostgroup = grab_request_var("hostgroup", "");
$servicegroup = grab_request_var("servicegroup", "");

$hosts = get_problem_hosts();
$services = get_problem_services();

if ($select_state == 'Show Problems') {
    $hosts = get_problem_hosts();
    $services = get_problem_services();
} else if ($select_state == 'Show All') {
    $hosts = forceimmediatecheck_get_hosts();
    $services = forceimmediatecheck_get_services();
}

if ($hostgroup != "") {
    $host_id = get_hostgroup_member_ids($hostgroup);
}

if ($servicegroup != "") {
    $service_id = get_servicegroup_member_ids($servicegroup);
}
// hostgroup filter, get hosts by host_id
if (!empty($host_id)) {
    $hosts = array_filter($hosts, function ($data) use ($host_id) {
        return in_array($data['host_id'], $host_id);
    });
}
// servicegroup filter, get services by service_id
if (!empty($service_id)) {
    $services = array_map(function($data) use ($service_id)
        { return array_filter($data, function($sub) use ($service_id)
            { return in_array($sub["service_id"], $service_id); });
    }, $services); 
}

foreach ($filtered_services as $value) {
    if (empty($value)) {
        $hosts = ""; 
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['forceCheckButton'])) {
        force_check();
    }
}

if (is_readonly_user(0)) {
    $html = _("You are not authorized to use this component.");
} else {
    $html = forceimmediatecheck_build_html($hosts, $services);
}

print $html;

////////////////////////////////////////////////////////////////////////
//  FUNCTIONS
////////////////////////////////////////////////////////////////////////

function force_check()
{    
    global $cfg;
    $pipe = $cfg['component_info']['nagioscore']['cmd_file'];
    $output = array();
    $now = time();
    $service_command = 'SCHEDULE_FORCED_SVC_CHECK';
    $host_command = 'SCHEDULE_FORCED_HOST_CHECK';
        
    // Check if file exists before trying
    if (!file_exists($pipe)) {        
        echo feedback_html('The Nagios process is likely not running. Cannot connect to nagios.cmd pipe.', true);
        return;
    }

    if (!empty($_POST['services'])) {
        foreach($_POST['services'] as $selected) {
            $sc_string = "/bin/echo " . escapeshellarg("[$now] $service_command;$selected;$now") . " > $pipe";            
            exec($sc_string, $output, $returncode);
        }
    }

    if (!empty($_POST['hosts'])) {
        foreach($_POST['hosts'] as $selected) {
            $hc_string = "/bin/echo " . escapeshellarg("[$now] $host_command;$selected;$now") . " > $pipe";            
            exec($hc_string, $output, $returncode);
        }
    }

    if (!empty($_POST['services']) || (!empty($_POST['hosts']))) {
        echo feedback_html('Check command submitted successfully.', false);
    } else {
        echo feedback_html('Please select at least one host/service.', true);
    }
}

function forceimmediatecheck_build_html($hosts, $services)
{      
    $html = "
    <h1>Force Immediate Check</h1>
    <div>Use this tool to force an immediate check on large groups of hosts/services.</div>
    <form id='form_masscheck' action='index.php' method='post'>

    <div style='margin: 10px 0 0 0;'> 
    <input type='hidden' id='submitted' name='submitted' value='true' />
        <div>
            <div style='padding: 20px 0 10px 0;'>                
                <input type='button' style='width:145px' class='btn btn-sm btn-default fl' id='selectAllButton'  title='" . _('Select All Hosts and Services') . "' value='" . _("Select All") . "'>
                <input type='submit' style='width:145px' class='btn btn-sm btn-primary' id='forceCheckButton' name='forceCheckButton' title='" . _('Force Check Selected') . "' value='" . _("Force Check") . "'>
            </div>     

            <table class='table table-condensed table-striped table-bordered table-auto-width' id='massforce_table'>
                <thead>
                    <tr class='center'>
                        <th class='center'>" . _("Host") . "</th>
                        <th class='center'>" . _("Service") . "</th>
                        <th class='center'>" . _("Last Check") . "</th>
                        <th class='center'>" . _("Status Information") . "</th>
                    </tr>
                </thead>
                <tbody>";

    $hostcount = 0;

    foreach ($hosts as $host) {

        $host_class = host_class($host['host_state']);

        $html .= "
            <tr>
                <td class='{$host_class}'>
                    <div class='checkbox'>
                        <label>
                            <input type='checkbox' name='hosts[]' value='{$host['host_name']}'>{$host['host_name']}
                        </label>
                    </div>
                </td>
                <td>
                    <div class='checkbox'>
                        <label>
                            <input class='host parent host{$hostcount}' data-id='{$hostcount}' id='selectAllHost' type='checkbox' name='hosts[]' value='{$host['host_name']}'>Select all {$host['host_name']} services
                        </label>
                    </div>
                </td>
                <td>
                    {$host['last_check']}
                </td>
                <td>
                    {$host['plugin_output']}
                </td>
            </tr>
            ";

        if (isset($services[$host['host_name']])) {
            foreach ($services[$host['host_name']] as $service) {
                $html .= " 
                <tr>  
                    <td></td>
                    <td class='" . service_class($service['current_state']) . "'>
                        <div class='checkbox'>
                            <label>
                                <input class='host child host{$hostcount}' data-id='{$hostcount}' type='checkbox' name='services[]' value='{$host['host_name']};{$service['service_description']}'>
                                {$service['service_description']}
                            </label>
                        </div>
                    </td>
                    <td>
                        <div class='last_check'>{$service['last_check']}</div>
                    </td>
                    <td>
                        <div class='plugin_output'>{$service['plugin_output']}</div>
                    </td>
                </tr>";
            }
            
        } $hostcount++;

    }
    $html .= "</tbody></table></div><div class='clear'></div></form>";

    return $html;
}

function feedback_html($msg, $error)
{
    if ($error) {
        $class = 'errorMessage';
        $icon = "<img src='" . theme_image("critical_small.png") . "'>";
    } else {
        $class = 'actionMessage';
        $icon = "<img src='" . theme_image("info_small.png") . "'>";
    }

    $feedback = "<div class='{$class} standalone'>{$icon} {$msg}</div>";           

    return $feedback;
}

function forceimmediatecheck_get_hosts()
{
    $backendargs["cmd"] = "gethoststatus";
    $xml = get_xml_host_status($backendargs);

    if ($xml) {
        foreach ($xml->hoststatus as $x) {

            $hosts["$x->name"] = array('host_state' => "$x->current_state", 
                'host_name' => "$x->name",
                'plugin_output' => "$x->status_text",
                'last_check' => "$x->last_check",
                'host_id' => "$x->host_id");
        }
    } 

    return $hosts;
}

function forceimmediatecheck_get_services()
{
    $backendargs["cmd"] = "getservicestatus";
    $xml = get_xml_service_status($backendargs);
    if ($xml) {
        foreach ($xml->servicestatus as $x) {
            $host_state = intval($x->host_current_state);
            $service = array('host_name' => "$x->host_name",
                'service_description' => "$x->name",
                'current_state' => "$x->current_state",
                'plugin_output' => "$x->status_text",
                'last_check' => "$x->status_update_time",
                'service_id' => "$x->service_id");
            $services["$x->host_name"][] = $service;
        }
    }

    return $services;  
}

function get_problem_hosts()
{
    // get all hosts in state 1 or 2
    $backendargs["current_state"] = "in:1,2";
    $xml = get_xml_host_status($backendargs);
    if ($xml) {
        foreach ($xml->hoststatus as $x) {
           
            $problem_hosts["$x->name"] = array('host_state' => "$x->current_state", 
                'host_name' => "$x->name",
                'plugin_output' => "$x->status_text",
                'last_check' => "$x->last_check",
                'host_id' => "$x->host_id");
        }
    }    
    // get all hosts(regardless of state) that have problem services
    $problem_services = get_problem_services();
    $all_hosts = forceimmediatecheck_get_hosts();

    if(isset($problem_services, $all_hosts)) {
        foreach ($problem_services as $value) {
            foreach ($value as $problem_services_host) {

                $problem_host_name = $problem_services_host['host_name'];
                $host_status = $all_hosts["$problem_host_name"]['host_state'];
                $plugin_output = $all_hosts["$problem_host_name"]['plugin_output'];
                $last_check = $all_hosts["$problem_host_name"]['last_check'];
                $host_id = $all_hosts["$problem_host_name"]['host_id'];

                $problem_hosts["$problem_host_name"] = array('host_state' => "$host_status", 
                    'host_name' => "$problem_host_name",
                    'plugin_output' => "$plugin_output",
                    'last_check' => "$last_check",
                    'host_id' => "$host_id");
            }
        }
    }
                
    return $problem_hosts;
}

function get_problem_services()
{
    $backendargs["current_state"] = "in:1,2,3";
    $xml = get_xml_service_status($backendargs);

    if ($xml) {
        foreach ($xml->servicestatus as $x) {
            $host_state = intval($x->host_current_state);
            $service = array('host_name' => "$x->host_name",
                'service_description' => "$x->name",
                'current_state' => "$x->current_state",
                'plugin_output' => "$x->status_text",
                'last_check' => "$x->status_update_time",
                'service_id' => "$x->service_id");
            $problem_services["$x->host_name"][] = $service;
        }
    }

    return $problem_services;    
}

function host_class($code)
{
    switch ($code) {
        case 0:
            return "hostup";
        case 1:
            return 'hostdown';
        default:
            return 'hostunreachable';
    }
}

function service_class($code)
{
    switch ($code) {
        case 0:
            return "serviceok";
        case 1:
            return 'servicewarning';
        case 2:
            return 'servicecritical';
        default:
            return 'serviceunknown';
    }
}

?>

<script  type="text/javascript">

// Select all
$(document).ready(function(){
    $('#selectAllButton:button').toggle(function(){
        $('input:checkbox').attr('checked','checked');
        $(this).val('Unselect All');
    },function(){
        $('input:checkbox').removeAttr('checked');
        $(this).val('Select All');
    });
});

// Select all services by host
$(document).ready(function() {
    // change a parent checkbox
    $('.host.parent').change(function() {
        // grab the id and checked value
        const id = $(this).data('id');
        const checked = $(this).prop('checked');
        // toggle all the children with the same id
        $(`.host.child[data-id=${id}]`).prop('checked', checked || false);
    });
});

</script>
