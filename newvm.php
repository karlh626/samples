#!/usr/bin/php
<?php
    /////////////////////////////////////
    // file: /usr/local/bin/newvm.php
    // version: 0.6.5
    // author: kharris
    // date: 2016-06-15
    // desc: setup a newly deployed VM (ip, hostname)
    //   and add basic info to RackTables
    // desc2: option if os is already setup to pull existing
    //   info and populate RT
    // 2016-09-13 - kharris fixed syntax on line 248
    //////////////////////////////////// 


    // Attempt to read values from /root/description.txt    
    if ( file_exists("/root/description.txt") ) {
        echo "------------------------------------------------------------------------\n";
        echo "\ty) update\n";
        echo "\tn) run entire setup\n";
        echo "(y/n) ";
        $so = trim(fgets(STDIN));

        if ( $so == "y" ) {
            // parse description.txt into variables
            $file_content = file_get_contents("./description.txt");
            $array = preg_split("/\n/", $file_content);

            list($a, $hostname1) = explode(":", $array[0]);
            list($a, $domain1) = explode(":", $array[1]);
            list($a, $host1) = explode(":", $array[2]);
            list($a, $ip1) = explode(":", $array[3]);
            list($a, $netmask1) = explode(":", $array[4]);
            list($a, $gateway1) = explode(":", $array[5]);
            list($a, $description1) = explode(":", $array[6]);

            $hostname1 = trim($hostname1);
            $domain1 = trim($domain1);
            $host1 = trim($host1);
            $ip1 = trim($ip1);
            $netmask1 = trim($netmask1);
            $gateway1 = trim($gateway1);

            echo "";
            echo "=================================================\n";
            echo "\tChange Options   \n";
            echo "\t--------------\n";
            echo "\te) hostname: $hostname1\n";
            echo "\tf) domain: $domain1\n";
            echo "\tb) ip: $ip1\n";
            echo "\tc) netmask: $netmask1\n";
            echo "\td) gateway: $gateway1\n";
            echo "\ta) host: $host1\n";
            echo "\tg) description:\n";
            echo "\t\t $description1\n";
            echo "=================================================\n";
        }
    }

    // Is this VM for devlab or production
    echo "Enter 1 for DevLab or 2 for Production:\n";
    echo "\t1 - DevLab VM\n";
    echo "\t2 - Production\n";
    $purpose = trim(fgets(STDIN));
    switch ($purpose) {
        case '1':
            $purpose = "dev";
            break;
        case '2':
            $purpose = "prod";
            break;
        default:
            echo "you must enter either 1 or 2";
            exec("/usr/local/bin/newvm.php");
            break;
    }

    // Pick Tasks: New or Existing
    echo "Select Type of Operation:\n";
    echo "\t1 - New Deployment\n";
    echo "\t2 - Just add to RackTables\n";
    $task = trim(fgets(STDIN));

	//******* Begin Config Section ***************
    if ($purpose == "dev") {
		$RTServer = "192.168.200.21:8080";  //IP address of RackTables Server
		$NTPServer1 = "192.168.200.5";
		$NTPServer2 = "";
		$DNS1 = "192.168.200.4";
		$DNS2 = "";
        if (is_file("/etc/yum.repos.d/vmware.repo")) {
            unlink("/etc/yum.repos.d/vmware.repo"); //this file contains links to a url only in the IWDN network
        }
    } else {
		$RTServer = "10.1.245.11";  //IP address of RackTables Server
		$NTPServer1 = "10.1.240.100";
		$NTPServer2 = "10.1.240.200";
		$DNS1 = "10.1.240.100";
		$DNS2 = "10.1.240.200";
    }
	//*******  End Config Section  ***************

    // send info to racktables
	function sendToRT($name, $domain, $ip, $mac, $vmware_host, $RT, $desc)
	{
        echo "name: " . $name . "\n";
            echo "domain: " . $domain . "\n";
            echo "ip: " . $ip . "\n";
            echo "mac: " . $mac . "\n";
            echo "host: " . $vmware_host . "\n";
            echo "rt: " . $RT . "\n";
            echo "desc: " . $desc . "\n";	
		$fqdn = $name . "." . $domain;
		$data = array(
			"ipAddress"=>"$ip", 
			"deviceName"=>"$name",
			"portName"=>"eth0",
			"macAddress"=>"$mac",
			"FQDN"=>"$fqdn",
			"container"=>"$vmware_host",
            "comment"=>"$desc"
		);
		// use php curl to send info

        echo "Connecting to RT...\n";

		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, "http://$RT/newhost/newhost.php");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$response = curl_exec($ch);

		echo "error: " . curl_error($ch) . "\n";
		curl_close($ch); 
		return $response;
	}

    // Just add to RT
    if ($task == 2) {
        //pull os info
        $hostname = exec("hostname");
        $ip = `ip -4 -o addr show dev eth0 | awk '{ gsub(/\/[0-9]+$/, "", $4); printf $4 }'`;
		$domain = `hostname | cut -f2-5 -d"."`;
        echo "hostname: " . $hostname . "\n";
	    echo "Local IP: " . $ip;
	    echo "Domain name: " . $domain . "\n";

        ob_start();
        passthru('/sbin/ifconfig | grep HWaddr');
        $dump = ob_get_contents();
        preg_match('/[A-F0-9]{2}:[A-F0-9]{2}:[A-F0-9]{2}:[A-F0-9]{2}:[A-F0-9]{2}:[A-F0-9]{2}/i', $dump, $mac);
        $macaddr = $mac[0];
        ob_end_clean();

        echo "Short Description:\n";
        // pull description from /root/description.txt file
        $description = trim(fgets(STDIN));       
        echo "VMware Container IP: ";
        $container = trim(fgets(STDIN));

        //send to RT
	    $response = sendToRT($hostname, $domain, $ip, $macaddr, $container, $RTServer, $description);
        echo $response . "\n";
        exit;
    }

    // get hostname
    echo "Enter Hostname: ";
    $hostname = trim(fgets(STDIN));

    // get domain name
    echo "Enter Domain Name: ";
    $domain = trim(fgets(STDIN));

    //get vmware host ip
    echo "Enter IP of VMware host this VM is on: ";
    $container = trim(fgets(STDIN));

    // get ip address
    echo "Enter IP Address: ";
    $ip = trim(fgets(STDIN));
    // check if ip is aleady in racktables - loop until an unused ip is given

    echo "Enter Gateway Address: ";
    $gateway = trim(fgets(STDIN));

    // get netmask
    echo "Enter Netmask: ";
    $netmask = trim(fgets(STDIN));

    // get description
    echo "Enter Description (Single Line - do not hit Enter until done): ";
    $description = trim(fgets(STDIN));
    // get Time Zone
    echo "\nPlease select the correct time zone:\n";
    echo "\t1. Eastern\n";
    echo "\t2. Central\n";
    echo "\t3. Mountain\n";
    echo "\t4. Arizona\n";
    echo "\t5. Pacific\n";
    echo "\t6. Alaska\n";
    echo "\t7. Hawaii\n";
    echo "\t8. ** UTC **\n";
    echo "\t9. Continue without setting time zone.\n";
    echo "Selection:";

    $tz = trim(fgets(STDIN));

    switch ($tz){
        case '1':
            echo "\nSetting time zone to Eastern.  Linking /usr/share/zoneinfo/America/New_York to /etc/localtime\n";
            `ln -sf /usr/share/zoneinfo/America/New_York /etc/localtime`;
            break;
        case '2':
            echo "\nSetting time zone to Central.  Linking /usr/share/zoneinfo/America/Chicago to /etc/localtime\n";
            `ln -sf /usr/share/zoneinfo/America/Chicago /etc/localtime`;
            break;
        case '3':
            echo "\nSetting time zone to Mountain.  Linking /usr/share/zoneinfo/America/Denver to /etc/localtime\n";
            `ln -sf /usr/share/zoneinfo/America/Denver /etc/localtime`;
            break;
        case '4':
            echo "\nSetting time zone to Arizona.  Linking /usr/share/zoneinfo/US/Arizona to /etc/localtime\n";
            `ln -sf /usr/share/zoneinfo/US/Arizona /etc/localtime`;
            break;
        case '5':
             echo "\nSetting time zone to Pacific.  Linking /usr/share/zoneinfo/America/Los_Angeles to /etc/localtime\n";
             `ln -sf /usr/hsare/zoneinfo/America/Los_Angeles /etc/localtime`;
             break;
        case '6':
             echo "\nSetting time zone to Alaska.  Linking /usr/share/zoneinfo/US/Alaska to /etc/localtime\n";
             `ln -sf /usr/share/zoneinfo/US/Alaska /etc/localtime`;
             break;
        case '7':
             echo "\nSetting time zone to Hawaii.  Linking /usr/share/zoneinfo/US/Hawaii to /etc/localtime\n";
             `ln -sf /usr/share/zoneinfo/US/Hawaii /etc/localtime`;
             break;
        case '8':
            echo "\nSetting the time zone to UTC.  Linking /usr/share/zoneinfo/UTC to /etc/localtime\n";
            `ln -sf /usr/share/zoneinfo/UTC /etc/localtime`;
        case '9':
             echo "\n\nContinuing without changing timezone.\n";
    }

    // add IP and hostname to description
    $description .= "\n" . $ip . "\n" . $hostname . "." . $domain;
    // write description to /etc/description.txt
    $fh = fopen("/root/description.txt", 'w');
    fwrite($fh, $description);
    fclose($fh);

    // add info to /etc/hosts file
    $fh = fopen("/etc/hosts", 'w');
    // ToDo: check if ip is aleady in racktables
    $hostString = "127.0.0.1    localhost   localhost.localdomain   localhost4  localhost4.localdomain4\n";
    $hostString .= "::1     localhost   localhost.localdomain   localhost6  localhost6.localdomain6\n";
    $hostString .= $ip . "\t" . $hostname . "." . $domain . "\t" . $hostname . "\n";
    fwrite($fh, $hostString);
    fclose($fh);

	// set hostname var
	exec("hostname $hostname");

    // add to /etc/sysconfig/network
    unset($fh);
    unset($hostString);
    $fh = fopen("/etc/sysconfig/network", 'w');
    $hostString = "NETWORKING=yes\nHOSTNAME=" . $hostname . "\n";
    $hostString .= "GATEWAY=" . $gateway . "\n";
    fwrite($fh, $hostString);
    fclose($fh);

    // edit /etc/sysconfig/network-scripts/ifcfg-eth0
    unset($fh);
    unset($hostString);
    $fh = fopen("/etc/sysconfig/network-scripts/ifcfg-eth0", 'w');
    $hostString = "DEVICE=\"eth0\"\nBOOTPROTO=\"static\"\nIPV6INIT=\"no\"\nMTU=\"1500\"\nNM_CONTROLLED=\"yes\"\nONBOOT=\"yes\"\n";
    $hostString .= "TYPE=\"Ethernet\"\nIPADDR=\"" . $ip . "\"\n";
    $hostString .= "NETMASK=\"" . $netmask . "\"\n";
    //$hostString .= "DNS1=\"" . $DNS1 . "\"\n";       //JBarnes requested not to have the DNS settings in ifcfg-eth0 (just in resolv.conf)
    //if ($DNS2 != "") {
    //    $hostString .= "DNS2=\"" . $DNS2 . "\"\n";
    //}
    fwrite($fh, $hostString);
    fclose($fh);
    
    // edit the /etc/udev/rules.d/70*net file
    if (is_file("/etc/udev/rules.d/70-persistent-net.rules")) {
        # if line contains eth1, delete eth0 line then change eth1 to eth0  
        $lines_rules = file("/etc/udev/rules.d/70-persistent-net.rules");
        foreach ($line_rules as $line) {
            if (preg_match("eth1", $line)) {
                foreach ($line_rules as $line2) {
                    if (preg_match("eth0", $line2)) {
                        # if line contains eth0 do nothing
                    } else {
                        # if line does not contain eth0 then add it to a new array

                    }
                }
            }
        }
    }


	// set sshd to start on boot
    echo "making sure that sshd starts on boot...\n";
	exec("/sbin/chkconfig sshd on");
	// set nrpe to start on boot
    echo "making sure that nrpe starts on boot...\n";
	exec("/sbin/chkconfig nrpe on");

    // set dns servers
    unset($fh);
    unset($string);

    $string = "nameserver $DNS1\n";
    if ($DNS2 != "") {
        $string .= "nameserver $DNS2\n";
    }
    $string .= "search $domain";

    echo "setting dns in /etc/resolv.conf...\n";
    $fh = fopen("/etc/resolv.conf", "w");
    fwrite($fh, $string);
    fclose($fh);

    // set ntp servers
    echo "setting ntpd to start on boot...\n";
    exec("/sbin/chkconfig ntpd on");
    echo "editing ntp.conf...\n";
    $file = file("/etc/ntp.conf");
    $newLines = array();
    foreach ($file as $line) {
        if (preg_match("/^server/", $line) === 0)
            $newLines[] = chop($line);
    }
    $newLines[] = "server $DNS1";
    if ($DNS2 != "") {
        $newLines[] = "server $DNS2";
    }
    for ($i = 0; $i < 4; $i++) {
        $newLines[] = "server $i.centos.pool.ntp.org iburst";
    }
    $newFile = implode("\n", $newLines);
    file_put_contents("/etc/ntp.conf", $newFile);

	// network service needs to be restarted so it has access to the network using the newly assigned settings
    echo "restarting network service...";
	exec("/sbin/service network restart");

	// get mac address after system is restarted
	$macaddr =  exec("cat /sys/class/net/eth0/address");

    //$z = trim(fgets(STDIN));

    echo "installing ntp...\n";
    exec("/usr/bin/yum -y install ntp");

	// test connection to racktables
	function ping($host)
	{
		exec(sprintf('ping -c 1 -W 5 %s', escapeshellarg($host)), $res, $rval);
		return $rval === 0;
	}

	// added by JBC to clean up IP of RTServer so ping will work  - changed ping parameter to $RTServerip
	$position = stripos($RTServer, ":");
	if ($position !== false) {
		$RTServerip = substr($RTServer,0,$position);
	} else {
		$RTServerip = $RTServer;
	}
	// end section added by JBC

	$connection = ping($RTServerip);
	if ($connection == 1) {
		// Send info to RT
		echo "sending info to RT\n";
		$response = sendToRT($hostname, $domain, $ip, $macaddr, $container, $RTServer, $description);
		echo $response . "\n";
	} else {
		echo "*************************************\n";
		echo "*** Can Not Connect to RackTables ***\n";
		echo "*** is this new vm in the DevLab? ***\n";
		echo "*************************************\n";
	}
       
   // REBOOT
    echo "*******************************\n";
    echo "****     reboot now        ****\n";
    echo "*******************************\n";

?>
