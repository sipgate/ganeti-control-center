function isNumber(n) {
	return /^-?[\d.]+(?:e-?\d+)?$/.test(n);
}

function bytesToSize(bytes) {
	var sizes = ['MB', 'GB', 'TB'];
	if (bytes == 0) return 'n/a';
	var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
	return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[[i]];
}

function updateJobStatusModal() {
	var jobId = $( "#jobStatusModal" ).data( "jobId" );
	$.getJSON("/jobStatus/" + jobId , function(data) {
		$( "#jobStatusModal").find( ".modal-body" ).html( "<p>Current Job State: <span class=\"job_" + data + "\">" + data + "</span><img class=\"pull-right\" id=\"jobStatusLoader\" src=\"/static/img/ajax-loader.gif\"></p>" );
		if (data != "success" && data != "error") {
			setTimeout(updateJobStatusModal, 1000);
		}
		else {
			if (data == "success") {	
				setTimeout(function() {
					$( "#jobStatusModal").modal('hide');
					location.reload();
				}, 1000);
			}
			else {
				$( "#jobStatusLoader" ).remove();
			}
		}
	});
}

function processResult(data) {
	if( isNumber(data) && data > 0) {
		$( "#jobStatusModal" ).find( ".modal-title" ).text( "Job Status for #" + data );
		$( "#jobStatusModal" ).data( "jobId", data);
		$( "#jobStatusModal").find( ".modal-body" ).html( "<p><img class=\"pull-right\" id=\"jobStatusLoader\" src=\"/static/img/ajax-loader.gif\"></p>" );
		setTimeout(updateJobStatusModal, 1000);
		$( "#jobStatusModal" ).modal();
	}
	else {
		$( "#jobStatusModal").find( ".modal-title" ).text( "Error" );
		$( "#jobStatusModal").data( "jobId", '-1');
		if(data.message) {
			var errorMsg = "Error-Code: " + data.code + "<br>\n" + "Message: " + data.message;
		}
		else {
			var errorMsg = "The request failed (unknown reason)";
		}
		$( "#jobStatusModal").find( ".modal-body" ).html( "<div class=\"alert alert-danger\" role=\"alert\">" + errorMsg + "</div>" );
		$( "#jobStatusModal").modal();
	}
}

function setInstanceParameter(instance, type, param, value) {
	$.post ( "/instanceParameter/" + instance + "/" + type + "/" + param + "/" + value, null, processResult, 'json');
}

function changeInstanceStatus(instance, instanceAction) {
	$.post ( "/changeInstanceStatus/" + instance, { 'action': instanceAction }, processResult, 'json')
}

function updateNic(instance, nicId, mac, link) {
	$.post ( "/updateNic/" + instance + "/" + nicId + "/" + mac + "/" + link, null, processResult, 'json')
}

function setClusterHvParameter(type, param, value) {
	$.post ( "/clusterHvParameter/" + type + "/" + param + "/" + value, null, processResult, 'json');
}

function setClusterIpolicyParameter(param, value) {
	$.post ( "/clusterIpolicyParameter/" + param + "/" + value, null, processResult, 'json');
}

$( document ).ready(function() {
	$( ".paramChange" ).click(function() {
		var instance = $(this).data("instance");
		var type = $(this).data("type");
		var param = $(this).data("param");
		var value = $(this).data("value");
		setInstanceParameter(instance, type, param, value);
	});

	$( ".instanceAction" ).click(function() {
		var instance = $(this).data("instance");
		var instanceAction = $(this).data("instanceaction");
		$.confirm({
			text: "Are you sure you want to perform the action '" + instanceAction + "' on Instance '" + instance + "'?",
			confirm: function() { changeInstanceStatus(instance, instanceAction); },
		});

	});
});
