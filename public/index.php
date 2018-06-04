<?php

# ganeti-control-center
# Copyright (C) 2016 sipgate GmbH
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

require_once('../vendor/autoload.php');

require_once('/etc/ganeti-control-center/config.inc.php');
require_once('../includes/ganetiClient.php');

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(array(
	'templates.path' => '../tpl'
));

$app->view(new \Slim\Views\Twig());
$app->view->parserExtensions = array(new \Slim\Views\TwigExtension());

$clusterIndex = $app->getCookie('clusterIndex');
if(!isset($clusterIndex) || !isset($config["rapi"][$clusterIndex])) {
	$clusterIndex = 0;
	$app->setCookie('clusterIndex', '0', '365 days');
}
$config["rapi-current"] = $config["rapi"][$clusterIndex];


$app->get('/', function() use ($app) {
	$app->redirect("/instances");
});

$app->get('/instances(/:filter(/:value))', function($filter = "none", $value = "") use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$cluster = $g->getClusterInfo();
	$clusterName = $g->getConfigName();
	$instances = $g->getInstances(true);
	$instancesFiltered = array();
	switch($filter) {
	case "byPrimaryNode":
		for($i = 0; $i < count($instances); $i++) {
			if($instances[$i]["pnode"] == $value) {
				$instancesFiltered[] = $instances[$i];
			}
		}
		$instances = $instancesFiltered;
		break;
	case "bySecondaryNode":
		for($i = 0; $i < count($instances); $i++) {
			if($instances[$i]["snodes"][0] == $value) {
				$instancesFiltered[] = $instances[$i];
			}
		}
		$instances = $instancesFiltered;
		break;
	}
	$app->render('page_instances.html', array( "config" => $config,
		"clusterName" => $clusterName,
		"cluster" => $cluster,
		"instances" => $instances,
		));
});

$app->get('/instanceDetails/:h', function($instanceName) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$clusterName = $g->getConfigName();
	$instance = $g->getInstance($instanceName);
	$pageTitle = $instanceName;
	if(isset($instance["nic.macs"][0])) {
		$mac = $instance["nic.macs"][0];
		$filename = "01-" . str_replace(":","-",$mac);
		$preseedConfigExists = file_exists("/var/lib/tftpboot/pxelinux.cfg/" . $filename);
	}
	else {
		$preseedConfigExists = false;
	}
	$app->render('page_instancedetails.html', array( "config" => $config,
		"clusterName" => $clusterName,
		"pageTitle" => $pageTitle,
		"instance" => $instance,
		"instance_dump" => print_r($instance,true),
		"preseedConfigExists" => $preseedConfigExists,
		"vlans" => $config["vlans"][$clusterName],
		"spiceCa" => $config["rapi-current"]["spice-ca"],
		));
});

$app->get('/clusterNodes', function() use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$cluster = $g->getClusterInfo();
	$clusterName = $g->getConfigName();
	$nodes = $g->getNodes(true);
	$groups = $g->getGroups(true);
	for($i = 0; $i < count($nodes); $i++) {
		if(!empty($nodes[$i]["group.uuid"])) {
			foreach($groups AS $group) {
				if($group["uuid"] == $nodes[$i]["group.uuid"]) {
					$nodes[$i]["group.name"] = $group["name"];
					$nodes[$i]["group.policy"] = $group["alloc_policy"];
					break;
				}
			}
		}
	}
	$app->render('page_clusternodes.html', array( "config" => $config,
		"clusterName" => $clusterName,
		"cluster" => $cluster,
		"nodes" => $nodes,
		"groups" => $groups,
		));
});

$app->get('/jobs', function() use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$cluster = $g->getClusterInfo();
	$clusterName = $g->getConfigName();
	$jobs = array_reverse($g->getJobs(true));
	$app->render('page_jobs.html', array( "config" => $config,
		"clusterName" => $clusterName,
		"cluster" => $cluster,
		"jobs" => $jobs));
});

$app->get('/jobDetails/:h', function($jobid) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$clusterName = $g->getConfigName();
	$job = $g->getJob($jobid);
	$app->render('page_jobdetails.html', array( "config" => $config,
		"clusterName" => $clusterName,
		"job" => $job,
		"job_dump" => print_r($job,true)));
});

$app->get('/jobStatus', function() use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$clusterName = $g->getConfigName();
	$jobs = array_reverse($g->getJobs(true));
	$status = array();
	foreach($jobs AS $job) {
		$status[$job["id"]] = $job["status"];
	}
	Header("Content-Type: application/json");
	echo json_encode($status);
});

$app->get('/jobStatus/:j', function($jobId) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$clusterName = $g->getConfigName();
	$job = $g->getJob($jobId);
	$status = $job["status"];
	Header("Content-Type: application/json");
	echo json_encode($status);
});

$app->get('/createInstance', function() use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$cluster = $g->getClusterInfo();
	$clusterName = $g->getConfigName();
	$app->render('page_createinstance.html', array( "config" => $config,
		"clusterName" => $clusterName,
		"vlans" => $config["vlans"][$clusterName],
		"cluster" => $cluster,
		));
});

$app->post('/createInstance', function() use ($app) {
	global $config;
    $g = new ganetiClient($config["rapi-current"]);

	$params = array(
        "iallocator" => "hail",
    );
    $instances = array();
    foreach($_POST["vmName"] AS $instanceName) {
	    $instances[] = array(
			"conflicts_check" => false,
			"disk_template" => "drbd",
			"ip_check" => false,
			"mode" => "create",
			"name_check" => false,
			"no_install" => true,
			"wait_for_sync" => false,
			"instance_name" => $instanceName,
            "os_type" => "debootstrap+default",
            "hypervisor" => "kvm",
			"beparams" => array(
				"vcpus" => $_POST["cpuCount"],
				"memory" => $_POST["memory"],
			),
			"nics" => array(
				array(
					"link" => "br" . $_POST["vlan"],
					"mac" => "generate"
				),
			),
			"disks" => array(
				array(
					"size" => ($_POST["diskSpace"]*1024)
				),
			),
			"hvparams" => array(
				"kernel_path" => "",
				"machine_version" => "pc",
				"nic_type" => "paravirtual"
            ),
        );
        if(!empty($_POST["instanceTag"])) {
            $key = count($instances) - 1;
            $instances[$key]["tags"] = array($_POST["instanceTag"]);
        }
    }
    $params["instances"] = $instances;
    $g->createMultiInstance($params);
	$app->redirect("/jobs");
});

$app->post('/changeInstanceStatus/:h', function($instance) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = -1;
	switch($_POST["action"]) {
	case "start":
		$return = $g->startInstance($instance);
		break;
	case "shutdown":
		$return = $g->shutdownInstance($instance);
		break;
	case "migrate":
		$return = $g->migrateInstance($instance);
		break;
	case "preseed":
		$inst = $g->getInstance($instance);
		if(!$inst["oper_state"]) {
			$mac = $inst["nic.macs"][0];
			$filename = "01-" . str_replace(":","-",$mac);
			if(copy("/etc/ganeti-control-center/pxe-boot-template.txt","/var/lib/tftpboot/pxelinux.cfg/" . $filename)) {
				$return = $g->startInstance($instance);
			}
		}
		break;
	}
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->get('/removePreseedConfig/:h', function($instance) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$inst = $g->getInstance($instance);
	$mac = $inst["nic.macs"][0];
	$filename = "01-" . str_replace(":","-",$mac);
	if(file_exists("/var/lib/tftpboot/pxelinux.cfg/" . $filename)) {
		unlink("/var/lib/tftpboot/pxelinux.cfg/" . $filename);
	}
	$app->redirect("/instanceDetails/" . $instance);
});

$app->get('/setCluster/:h', function($cluster) use ($app) {
	global $config;
	if(is_numeric($cluster) && isset($config["rapi"][$cluster])) {
		$app->setCookie("clusterIndex", $cluster, "365 days");
	}
	else {
		$app->setCookie("clusterIndex", "0", "365 days");
	}
	$app->redirect("/instances");
	exit;
});

$app->get('/getSpiceConfig/:i', function($instance) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$inst = $g->getInstance($instance);
	
	Header("Content-Type: application/x-virt-viewer");
	header("Content-disposition: attachment; filename=spice-connection.vv");
	echo "[virt-viewer]\n";
        echo "type=spice\n";
	echo "host=" . $inst["pnode"] . "\n";
	if($inst["hvparams"]["spice_use_tls"]) {
		echo "tls-port=" . $inst["network_port"] . "\n";
		echo "host-subject=CN=ganeti.example.com\n";
	}
	else {
		echo "port=" . $inst["network_port"] . "\n";
	}
	echo "delete-this-file=1\n";
	exit;
});

$app->post('/instanceParameter/:h/:t/:p/:v', function($instance, $type, $parameter, $value) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = -1;
	switch($type) {
	case "beparam":
		switch($parameter) {
		case "autoBalance":
			switch($value) {
			case "0":
				$return = $g->setInstanceParameter($instance, $type, "auto_balance", false);
				break;
			case "1":
				$return = $g->setInstanceParameter($instance, $type, "auto_balance", true);
				break;
			}
			break;
		case "spindleUseCount":
			if(is_numeric($value)) {
				$return = $g->setInstanceParameter($instance, $type, "spindle_use", $value);
			}
			break;
		case "cpuCount":
			if(is_numeric($value)) {
				$return = $g->setInstanceParameter($instance, $type, "vcpus", $value);
			}
			break;
		case "memoryAmount":
			if(is_numeric($value)) {
				$beparams = array(
					"memory" => $value,
					"minmem" => $value,
					"maxmem" => $value,
				);
				$return = $g->setInstanceParameters($instance, $beparams);
			}
			break;
		}
	break;
	case "hvparam":
		$return = $g->setInstanceParameter($instance, $type, $parameter, $value);
		break;
	}
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->post('/updateNic/:i/:n/:m/:l', function($instance, $nic, $mac, $link) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = $g->setNicParameters($instance, $nic, $mac, $link);
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->post('/addNic/:i/:m/:l', function($instance, $mac, $link) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = $g->addNic($instance, $mac, $link);
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->post('/growDisk/:i/:d/:a', function($instance, $disk, $amount) use ($app) {
	global $config;
    $g = new ganetiClient($config["rapi-current"]);
    $amount = $amount * 1024;
	$return = $g->growDisk($instance, $disk, $amount);
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->post('/addDisk/:i/:s', function($instance, $size) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = $g->addDisk($instance, $size);
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->post('/addTag/:i/:s', function($instance, $tag) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = $g->addTag($instance, $tag);
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->post('/clusterHvParameter/:t/:p/:v', function($type, $parameter, $value) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = $g->setClusterParameter("hvparams", $type, $parameter, $value);
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->post('/clusterIpolicyParameter/:p/:v', function($parameter, $value) use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$return = $g->setClusterParameter("ipolicy", "none", $parameter, $value);
	Header("Content-Type: application/json");
	echo json_encode($return);
	exit;
});

$app->get('/statistics', function() use ($app) {
	global $config;
	$g = new ganetiClient($config["rapi-current"]);
	$cluster = $g->getClusterInfo();
	$clusterName = $g->getConfigName();
	$stats = $g->getStats();

	$app->render('page_statistics.html', array( "config" => $config,
		"clusterName" => $clusterName,
		"vlans" => $config["vlans"][$clusterName],
		"cluster" => $cluster,
		"stats" => $stats,
		));
});


$app->run();

?>
