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

class ganetiClient {
	private $config;

	function ganetiClient($config) {
		$this->config = $config;
	}

	function callApi($method, $url, $data = false)
	{
		$curl = curl_init();

		switch ($method)
		{
		case "POST":
			curl_setopt($curl, CURLOPT_POST, 1);

			if ($data) {
				$data_string = json_encode($data);
				curl_setopt($curl, CURLOPT_HTTPHEADER, array(                                                                          
					'Content-Type: application/json',                                                                                
					'Content-Length: ' . strlen($data_string))                                                                       
				);

				curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
			}
			break;
		case "PUT":
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");

			if ($data) {
				$data_string = json_encode($data);
				curl_setopt($curl, CURLOPT_HTTPHEADER, array(                                                                          
					'Content-Type: application/json',                                                                                
					'Content-Length: ' . strlen($data_string))                                                                       
				);

				curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
			}

            break;
        case "PUT_W_GET_PARAMS":
            # this is only here to make the broken "tags" part of RAPI work
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");

			if ($data)
				$url = sprintf("%s?%s", $url, http_build_query($data));

            break;
		default:
			if ($data)
				$url = sprintf("%s?%s", $url, http_build_query($data));
		}

		// Optional Authentication:
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		if(isset($this->config["user"])) {
			curl_setopt($curl, CURLOPT_USERPWD, $this->config["user"] . ":" . $this->config["password"]);
		}

		curl_setopt($curl, CURLOPT_URL, "https://" . $this->config["host"] . ":5080" . $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);
		print_r(curl_error($curl));

		curl_close($curl);

		return $result;
	}

	function getConfigName() {
		return $this->config["name"];
	}

	function getClusterInfo() {
		$data = json_decode($this->callApi("GET","/2/info"),true);
		return $data;
	}

	function getNodes($bulk = false) {
		if($bulk) {
			$data = json_decode($this->callApi("GET","/2/nodes",array("bulk" => "1")),true);
		}
		else {
			$data = json_decode($this->callApi("GET","/2/nodes"),true);	
		}
		return $data;
	}

	function getInstances($bulk = false) {
		if($bulk) {
			$data = json_decode($this->callApi("GET","/2/instances",array("bulk" => "1")),true);
		}
		else {
			$data = json_decode($this->callApi("GET","/2/instances"),true);
		}
		return $data;
	}

	function getInstance($name) {
		$data = json_decode($this->callApi("GET","/2/instances/" . $name),true);
		return $data;
	}

	function getInstanceConsole($name) {
		$data = json_decode($this->callApi("GET","/2/instances/" . $name . "/console"),true);
		return $data;
	}

	function createInstance($params) {
		$data = json_decode($this->callApi("POST","/2/instances", $params),true);
		return $data;
	}

	function createMultiInstance($params) {
		$data = json_decode($this->callApi("POST","/2/instances-multi-alloc", $params),true);
		return $data;
	}

	function startInstance($instance) {
		$data = json_decode($this->callApi("PUT","/2/instances/" . $instance . "/startup"),true);
		return $data;
	}

	function shutdownInstance($instance, $timeout = 120) {
		$params = array(
			"timeout" => $timeout
		);
		$data = json_decode($this->callApi("PUT","/2/instances/" . $instance . "/shutdown", $params),true);
		return $data;
	}

	function failoverInstance($instance) {
		$data = json_decode($this->callApi("PUT","/2/instances/" . $instance . "/failover"),true);
		return $data;
	}

	function migrateInstance($instance) {
		$data = json_decode($this->callApi("PUT","/2/instances/" . $instance . "/migrate"),true);
		return $data;
	}

	function setInstanceParameter($instance, $type, $parameter, $value) {
		switch($type) {
		case "beparam":
			$data = array(
				"beparams" => array(
					$parameter => $value),
			);
			$return = json_decode($this->callApi("PUT","/2/instances/" . $instance . "/modify",$data), true);
			return $return;
			break;
		case "hvparam":
			$data = array(
				"hvparams" => array(
					$parameter => $value),
			);
			$return = json_decode($this->callApi("PUT","/2/instances/" . $instance . "/modify", $data), true);
			return $return;
			break;
		}
	}

	function setInstanceParameters($instance, $beparams = array(), $hvparams = array()) {
		if(!empty($beparams)) $data["beparams"] = $beparams;
		if(!empty($hvparams)) $data["hvparams"] = $hvparams;
		$return = json_decode($this->callApi("PUT","/2/instances/" . $instance . "/modify", $data), true);
		return $return;
	}

	function setClusterParameter($section, $type, $parameter, $value) {
		$return = -1;
		switch($section) {
		case "hvparams":
			$data = array(
				"hvparams" => array(
					$type => array(
						$parameter => $value,
					),
				),
			);
			$return = json_decode($this->callApi("PUT", "/2/modify", $data), true);
			break;
		case "ipolicy":
			$data = array(
				"ipolicy" => array(
					$parameter => $value
				),
			);
			$return = json_decode($this->callApi("PUT", "/2/modify", $data), true);
		}
		return $return;
	}

	function setNicParameters($instance, $nic, $mac, $link) {
		$data = array(
			"nics" => array(
				array(
					"modify",
					$nic,
					array(
						"link" => $link,
					),
				),
			),
		);

		$origInstance = $this->getInstance($instance);
		if($origInstance["nic.macs"][$nic] != $mac) {
			$data["nics"][0][2]["mac"] = $mac;
		}

		return json_decode($this->callApi("PUT","/2/instances/" . $instance . "/modify", $data), true);
	}

    function addNic($instance, $mac, $link) {
		$data = array(
			"nics" => array(
				array(
					"add",
					-1,
                    array(
                        "mac" => $mac,
						"link" => $link,
					),
				),
			),
		);

		$origInstance = $this->getInstance($instance);

		return json_decode($this->callApi("PUT","/2/instances/" . $instance . "/modify", $data), true);
    }

    function addDisk($instance, $size) {
		$data = array(
			"disks" => array(
				array(
					"add",
					-1,
                    array(
                        "size" => $size . "g"
					),
				),
			),
		);

		$origInstance = $this->getInstance($instance);

		return json_decode($this->callApi("PUT","/2/instances/" . $instance . "/modify", $data), true);
    }

    function addTag($instance, $tag) {
        $data["tag"] = $tag;
        return json_decode($this->callApi("PUT_W_GET_PARAMS","/2/instances/" . $instance . "/tags", $data), true);
    }

    function growDisk($instance, $disk, $amount) {
        $data = array(
            "amount" => $amount,
            "absolute" => false,
            "wait_for_sync" => false,
        );

		return json_decode($this->callApi("POST","/2/instances/" . $instance . "/disk/" . $disk . "/grow", $data), true);
    }

	function getJobs($bulk = false) {
		if($bulk) {
			$data = json_decode($this->callApi("GET","/2/jobs",array("bulk" => "1")),true);
			for($i = 0; $i < count($data); $i++) {
				if(isset($data[$i]["summary"][0]) && preg_match('/^INSTANCE_.+\((.+)\)$/',$data[$i]["summary"][0],$hits)) {
					$data[$i]["instanceLink"] = $hits[1];
				}
				else {
					$data[$i]["instanceLink"] = false;
				}
			}
		}
		else {
			$data = json_decode($this->callApi("GET","/2/jobs"),true);
		}
		return $data;
	}

	function getJob($jobid) {
		$data = json_decode($this->callApi("GET","/2/jobs/" . $jobid),true);
		return $data;
	}

	function getGroups($bulk = false) {
		if($bulk) {
			$data = json_decode($this->callApi("GET","/2/groups",array("bulk" => "1")),true);
		}
		else {
			$data = json_decode($this->callApi("GET","/2/groups"),true);
		}
		return $data;
	}

	function getStats() {
		$stats["instance-count"] = 0;
		$stats["instances"]["memory"] = 0;
		$stats["instances"]["disk"] = 0;
		$stats["instances"]["cpus"] = 0;
		$stats["node-count"] = 0;
		$stats["nodes"]["memory"] = 0;
		$stats["nodes"]["disk"] = 0;
		$stats["nodes"]["diskfree"] = 0;
		$stats["nodes"]["cpus"] = 0;


		# load instances
		$instances = $this->getInstances(true);
		foreach($instances AS $instance) {
			# sum up memory/cpus (cluster-wide)
			$stats["instance-count"]++;
			$stats["instances"]["memory"] += $instance["beparams"]["memory"];
			$stats["instances"]["cpus"] += $instance["beparams"]["vcpus"];

			# sum up memory/cpus (per node)
			isset($stats["pernode"][$instance["pnode"]]["memory"]) ? $stats["pernode"][$instance["pnode"]]["memory"] += $instance["beparams"]["memory"] : $stats["pernode"][$instance["pnode"]]["memory"] = $instance["beparams"]["memory"];
				isset($stats["pernode"][$instance["pnode"]]["vcpus"]) ? $stats["pernode"][$instance["pnode"]]["vcpus"] += $instance["beparams"]["vcpus"] : $stats["pernode"][$instance["pnode"]]["vcpus"] = $instance["beparams"]["vcpus"];

				# same for any disk(s) that might exist
				foreach($instance["disk.sizes"] AS $disk) {
					$stats["instances"]["disk"] += $disk;
					isset($stats["pernode"][$instance["pnode"]]["disk"]) ? $stats["pernode"][$instance["pnode"]]["disk"] += $disk : $stats["pernode"][$instance["pnode"]]["disk"] = $disk;
				}

			# count instances per node
			isset($stats["pnode-counter"][$instance["pnode"]]) ? $stats["pnode-counter"][$instance["pnode"]]++ : $stats["pnode-counter"][$instance["pnode"]] = 1;
		}
		# calculate instances-per-node-data
		$stats["min-inst-per-node"] = min($stats["pnode-counter"]);
		$stats["max-inst-per-node"] = max($stats["pnode-counter"]);
		$stats["avg-inst-per-node"] = round(array_sum($stats["pnode-counter"]) / count($stats["pnode-counter"]));
		unset($stats["pnode-counter"]);

		# load cluster nodes
		$nodes = $this->getNodes(true);
		foreach($nodes AS $node) {
			# sum up memory/cpu/disk totals (cluster-wide)
			$stats["node-count"]++;
			$stats["nodes"]["memory"] += $node["mtotal"];
			$stats["nodes"]["disk"] += $node["dtotal"];
			$stats["nodes"]["diskfree"] += $node["dfree"];
			$stats["nodes"]["cpus"] += $node["ctotal"];

			# save per-node data
			$stats["pernode"][$node["name"]]["memtotal"] = $node["mtotal"];
			$stats["pernode"][$node["name"]]["disktotal"] = $node["dtotal"];
			$stats["pernode"][$node["name"]]["cpus"] = $node["ctotal"];
		}

		# calculate memory/disk diffs
		$stats["nodes"]["memfree"] = $stats["nodes"]["memory"] - $stats["instances"]["memory"];
		foreach($stats["pernode"] AS $node => $data) {
			@$stats["pernode"][$node]["memfree"] = $data["memtotal"] - $data["memory"];
			@$stats["pernode"][$node]["diskfree"] = $data["disktotal"] - $data["disk"];
		}

		# calculate real-to-virtual-cpu-ratio
		$stats["nodes"]["cpu-ratio"] = round($stats["instances"]["cpus"] / $stats["nodes"]["cpus"],2);

		return $stats;
	}
 
}
?>
