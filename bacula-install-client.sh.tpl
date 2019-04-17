#!/bin/bash
# vim: set ft=sh:
# This is a template file used to create an install script for a bacula backup client on CentOS v6 for remote deployment

#config specifications
dirhostname="<%= $name %>"
backup_server="<%= $ip %>"
IPAddress="<%= $conf->{"localip"} %>"

#make sure to run as root user - if not then exit
if [ "$(id -u)" != "0" ]
then
	echo "============================="
	echo "This needs to be run as root!"
	echo "============================="
	exit 1
fi

echo -e '\nHost *\nStrictHostKeyChecking no\n' >> ~/.ssh/config
key=`ssh-keyscan $backup_server`
echo $key >> /root/.ssh/known_hosts

cd /usr/local/src
if [ ! -f /usr/local/src/bacula5213.tar.gz ]
then
    echo "Bacula Source code not uploaded"
    exit
fi

tar xzf bacula5213.tar.gz
cd bacula*

if [[ -f /etc/redhat-release ]]; then
    #install dep
    yum install -y gcc-c++ gcc readline-devel ncurses-devel zlib-devel

    #compile
    CFLAGS="-g -Wall" ./configure --sbindir=/usr/sbin --sysconfdir=/etc/bacula --with-scriptdir=/etc/bacula --enable-smartalloc --with-working-dir=/var/bacula --with-pid-dir=/var/run --enable-conio --disable-build-stored --disable-build-dird --enable-client-only
	#echo "Verify the settings above, then press Enter to continue or Ctrl-C to stop"
	#read x
    make
    make install
    make install-autostart-fd
fi

### Modify bacula-fd.conf file  ###
sed -i "s/$HOSTNAME-dir/$dirhostname-dir/g" /etc/bacula/bacula-fd.conf
sed -i "s/$HOSTNAME-mon/$dirhostname-mon/g" /etc/bacula/bacula-fd.conf

### Create file to include in bacula-dir.conf ###
cd /usr/local/src
mv bare-client-dir.conf $HOSTNAME.conf
sed -i "s/client-name/$HOSTNAME/g" $HOSTNAME.conf
IPAddress=`ifconfig eth0 | sed -n 's/.*inet addr:\([0-9.]\+\)\s.*/\1/p'`
sed -i "s/ip-address/$IPAddress/g" $HOSTNAME.conf

### GET Password ###
PassWord=`grep -m 1 "Password" /etc/bacula/bacula-fd.conf | cut -d'"' -f2`
sed -i "s;password;$PassWord;g" /usr/local/src/$HOSTNAME.conf
scp -i "/root/.ssh/bacula_rsa" $HOSTNAME.conf root@$backup_server:/etc/bacula/clients/

service bacula-fd restart

### Add client config include in bacula-dir.conf on server ###

## testing the following line on another script ##
scp -i "/root/.ssh/bacula_rsa" root@$backup_server:/etc/bacula/bacula-dir.conf /usr/local/src/bacula-dir.conf
echo "@/etc/bacula/clients/$HOSTNAME.conf" >> /usr/local/src/bacula-dir.conf
scp -i "/root/.ssh/bacula_rsa" /usr/local/src/bacula-dir.conf root@$backup_server:/etc/bacula/bacula-dir.conf
ssh -i "/root/.ssh/bacula_rsa" root@$backup_server /sbin/service bacula-dir restart

sed '/StrictHostKeyChecking\ no/d' ~/.ssh/config
sed '/Host\ \*/d' ~/.ssh/config

iptables -I INPUT 1 -p tcp -s $backup_server --dport 9102 -j ACCEPT
iptables-save > /etc/sysconfig/iptables
