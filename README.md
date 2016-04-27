# GANETI CONTROL CENTER

## What is this?

GCC is a frontend to manage ganeti clusters. Essentially, it is a web application based on Slim/Twig, that wraps around a PHP class ```ganetiClient```, which in turn connects to a cluster's RAPI daemon. The class could also be used in other projects/scripts. As of now, GCC does not store any data on its own. However, you need a TFTP server running on the same machine to use the 'preseed feature' (which starts an instance and creates a special tftp/pxe boot configuration for this host).

## Who should use this and who should not?

GCC is tightly build around existing infrastructure, therefore it makes certain assumptions or at least works best in this environment:
* Hypervisor: KVM
* Disk Templates: DRBD
* Instances: independent virtual machines with partition tables, a bootloader etc. (no deboostrap, no integration with ganeti whatsoever)
* Network: the nodes are connected to multiple instance VLANs, each with its own bridge interface namend ```br<VLAN``` on the node - instance interfaces are connected to these bridges
* Remote Console: Spice

## Features

* supports multiple ganeti clusters
* list all instances
* show instance details
* manage instances (create, start, shutdown, migrate)
* spice console integration (tested only on linux)
* list cluster nodes/groups
* list/manipulate cluster settings
* list jobs
* show cluster statistics

## Ganeti Setup

To enable the RAPI daemon on a Debian based system, please update your ```/etc/default/ganeti``` file to contain the following line:
```
RAPI_ARGS="--require-authentication"
```
You also need to create a user/password pair, which is well covered in the official [RAPI Documentation](http://docs.ganeti.org/ganeti/2.15/html/rapi.html#users-and-passwords).

The RAPI daemon does only run the master node - if your cluster master IP is reachable you can point GCC to this address. However, if your cluster uses a separate network for cluster communication the master's IP address might not be reachable from the outside. As of now, the only possible solution to this is to specify the current master in the GCC configuration and update it, when the master changes. In the future, GCC might figure out the available cluster nodes itself (once it has a working connection) and switch to a new master automagically. 

## Dependencies

* apache + mod-rewrite
* php5
* php5-curl
* TFTP Server

## GCC Setup

* Install dependencies 
* this repository
* rename ```config/config.inc.php.dist``` to ```config/config.inc.php``` and edit
* the webserver needs write access to the folder ```/var/lib/tftpboot/pxelinux.cfg```
* point your webserver's root to ```ganeti-control-center/public```
* configure HTTPS + LDAP auth in your webserver

## missing features / ideas / TODO

* destructive actions (e.g. instance removal)
* store all cluster nodes locally and remember the current master - switch automatically if it becomes unreachable
* DHCP integration: allocate an IP address for a new instance or at least figure out which IP address has been assigned by the DHCP server
* DNS integration: create DNS records via nsupdate? 

### Screenshots

[<img src="http://s15.postimg.org/mms00ql93/gcc_clusternodes.jpg">](http://s15.postimg.org/i0vvsdzq3/gcc_clusternodes.png)
[<img src="http://s8.postimg.org/98xr6m9g1/gcc_instancedetails.jpg">](http://s8.postimg.org/qm81lh4r9/gcc_instancedetails.png)
[<img src="http://s11.postimg.org/wvg91a467/gcc_instances.jpg">](http://s11.postimg.org/byk0wm65f/gcc_instances.png)
[<img src="http://s32.postimg.org/apwyrcmh1/gcc_stats.png">](http://s32.postimg.org/apwyrcmh1/gcc_stats.png)
