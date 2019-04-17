#!/bin/bash
# vim: set ts=4 sw=4 sts=4 :
# if you are reading this, I have some funny stories to tell about ghetto

###### Config Begin ######
backups_dir=/volume1/vol1/ghetto
days=45  #maximum age of files
exclude_dirs_array=( iso ghetto-master archive ) #ignore these directories located in $backups_dir
oldlist=/tmp/no-recent-ghetto-bkups
mailer=ssmtp   #app to use for mailing out the report
recipient=kharris@company.domain  #who is to receive the report/list of old backups
####### Config End #######

cat /dev/null > /tmp/no-recent-ghetto-bkups

exclude_dirs_array=("${exclude_dirs_array[@]/#/$backups_dir/}")

ip_dirs_array=() 

for d in "$backups_dir"/*
do
    if [[ -d "$d" && ! -L "$d" && ! "${exclude_dirs_array[@]}" =~ "$d" ]]
    then
	#echo $d

        for vm in "$d"/*
	    do
		    #echo $vm
		    # only work on directories	
		    if [ -d "$vm" ]
		    then
			    #$echo $vm	
			    for bkup in "$vm"/*
			    do
				    #echo $bkup
				    for file in "$bkup"/*
				    do
					    if [[ $(find "$file" -mtime +$days -print) ]]
					    then
                            #echo $file
                            echo $vm >> $oldlist
					    fi
				    done
			    done	
		    fi
	    done
    fi 
done

oldbkups=$( cat $oldlist | wc -l )
if [ $oldbkups -gt 0 ]
then
    #get rid of duplicate lines in /tmp/no-recent-ghetto-bkups
    cat $oldlist | uniq > $oldlist-sorted
    mv $oldlist-sorted $oldlist
    cat $oldlist | $mailer $recipient
    echo "$vm has backups older than $days days"
    exit 1
else
    echo "all ghetto backups are current"
    exit 0
fi
