#!/bin/bash
# script for installing a bacula backup server for a tiny deployment which only used sqlite db as the backup catalog

#make sure to run as root user - if not then exit
if [ "$(id -u)" != "0" ]
then
	echo "============================="
	echo "This needs to be run as root!"
	echo "============================="
	exit 1
fi

#Create ssh key pair is not exist already
if [ ! -f /root/.ssh/id_rsa.pub ]
then
	ssh-keygen -f /root/.ssh/id_rsa -t rsa -N ""
fi

cd /usr/local/src
tar xzf bacula5213.tar.gz
cd bacula*

if [[ -f /etc/redhat-release ]]; then
    #install dep
    yum -y install gcc-c++ gcc readline-devel ncurses-devel zlib-devel sqlite-devel

    #compile
    CFLAGS="-g -Wall" ./configure --sbindir=/usr/sbin --sysconfdir=/etc/bacula --with-scriptdir=/etc/bacula --enable-smartalloc --with-working-dir=/var/bacula --with-pid-dir=/var/run --enable-conio --with-sqlite3
	#echo "Verify the settings above, then press Enter to continue or Ctrl-C to stop"
	#read x
    make
    make install
    make install-autostart

    /etc/bacula/create_bacula_database
    /etc/bacula/make_bacula_tables
fi
