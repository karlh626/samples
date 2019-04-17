#!/bin/bash
#############################################################
# Author: kharris
# Date: Mon Nov 30 11:04:49 EST 2015
# File: wrk_log.sh
# Syntax: wrk_log.sh 
# Desc: posts work log to RackTable's logs for that particular
#   server
############################################################

RED='\033[0;31m'
WH='\033[1;37m'
YEL='\033[1;33m'
NC='\033[0m'
now=$(date +'%Y-%m-%d %H:%M:%S')
userid=$UID
if [[ $userid == 0 ]]
then
    user="admin"
else
    #find username matching userid
    user=$LOGNAME
fi
printf "${WH} ----------------------------------------------------${NC}\n"
printf "${WH}|${YEL} Enter details of the work performed on this server ${WH}|${NC}\n"
printf "${WH}|${YEL} which will be logged locally and on RackTables     ${WH}|${NC}\n"
printf "${WH} ----------------------------------------------------${NC}\n"
echo ""
echo "hostname: $(hostname)"
printf "Log Entry ${RED}(hit enter to add entry)${NC}:\n"
read description
echo ""
echo "writing locally to /tmp/wrk_log"
echo $now -- $description >> /tmp/wrk_log.$(date +'%Y%m%d-%H%M')
echo ""
echo "sending to RackTables"
output=$(curl -s --data "hostname=$(hostname)&log=$description&user=$user" http://racks.indigital.net/wrk_update/index.php)

printf "${WH} ----------------------------------------------------${NC}\n"
printf "${WH}* ${YEL}"
printf "$output"
printf "${WH} *${NC}\n"
printf "${WH} ----------------------------------------------------${NC}\n"

