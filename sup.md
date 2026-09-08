sudo nano /etc/supervisor/conf.d/cekDisit-worker.conf


[program:cekDisit-worker]

process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/scannerMT/artisan queue:work --sleep=3 --tries=3 --timeout=90

directory=/var/www/html/scannerMT

autostart=true
autorestart=true
stopasgroup=true
killasgroup=true

numprocs=2

user=www-data

redirect_stderr=true
stdout_logfile=/var/www/html/scannerMT/storage/logs/worker.log
stopwaitsecs=3600