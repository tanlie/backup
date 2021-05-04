#!/bin/bash
# user="root"
# password="JiSl#ieN"
# db_name="gogs"
# backup_path="/www/backup"
# date=$(date +%Y%m%d)
# file_name=$db_name-$date'.sql'
# umask 177
# mysql-dump --user=$user --password=$password $db_name > $backup_path/$file_name

docker exec -it 9176a3cb0b87 /bin/bash /etc/mysql/conf.d/gogsbackup.sh
