#!/bin/sh
if pidof -x $(basename $0) > /dev/null; then
 for p in $(pidof -x $(basename $0)); do
   if [ $p -ne $$ ]; then
     echo "Script $0 is already running: exiting"
     exit
   fi
 done
fi

LOGS='/var/ossim/logs/'
eval `egrep "^log_dir" /usr/share/ossim/www/sem/everything.ini `
if [ -d $log_dir ];then
	LOGS=$log_dir
fi

if [ "$1" != "--force" ];then
    cd /usr/share/ossim/scripts/sem/ && sh /usr/share/ossim/scripts/sem/forensic_stats_last_hour.sh
    cd /usr/share/ossim/scripts/sem/ && perl /usr/share/ossim/scripts/sem/generate_stats.pl $LOGS
else
    cd /usr/share/ossim/scripts/sem/ && sh /usr/share/ossim/scripts/sem/forensic_stats_last_hour-force.sh
    cd /usr/share/ossim/scripts/sem/ && perl /usr/share/ossim/scripts/sem/generate_stats.pl $LOGS force
fi
cd /usr/share/ossim/scripts/sem/ && perl /usr/share/ossim/scripts/sem/gen_sensor_totals.pl $LOGS
cd /usr/share/ossim/scripts/sem/ && perl /usr/share/ossim/scripts/sem/generate_sem_stats.pl $LOGS
cd /usr/share/ossim/scripts/sem/ && sh /usr/share/ossim/scripts/sem/update_db.sh
