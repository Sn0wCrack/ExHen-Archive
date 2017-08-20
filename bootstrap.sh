# Prevent apt-get from using stdin at all during setup
export DEBIAN_FRONTEND=noninteractive
# Project-specific variables
NEW_HOSTNAME="exhen"
MYSQL_DB="exhen"
MYSQL_HOST="127.0.0.1"
MYSQL_USER="root" # Should be "root" if using localhost mysql host
MYSQL_PASSWORD="changeme"

# End of configuration --- edit below with caution!

# Update apt cache
apt-get update

# Set mysql root password upfront to disable prompt from asking while installing mysql-server package
debconf-set-selections <<< "mysql-server mysql-server/root_password password $MYSQL_PASSWORD"
debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $MYSQL_PASSWORD"
apt-get -y install mysql-server

# Install the rest of the dependencies
apt-get -y install \
    apache2 \
    php5 \
    php5-mysql \
    php5-mcrypt \
    php5-curl \
    php5-imagick \
    php5-gd \
    php5-memcache \
    mysql-client \
    unixodbc \
    libpq5 \
    memcached \
    p7zip-full

# Update hostname
echo "127.0.1.1 $NEW_HOSTNAME" >> /etc/hosts
echo "$NEW_HOSTNAME" > /etc/hostname
hostname -F /etc/hostname

# Make required directories
if [ -d /vagrant/images ] || [ -d /vagrant/temp ];
then
    echo "required directories already exist"
else
    mkdir /vagrant/images
    mkdir /vagrant/temp
fi


# Set ServerName directive on apache globally to suppress warnings on start
if [ -f /etc/apache2/sites-available/server-name.conf ];
then
    echo "server-name.conf exists"
else
    mkdir -p /etc/apache2/conf-available && touch /etc/apache2/conf-available/server-name.conf
fi
echo "ServerName $(hostname)" > /etc/apache2/conf-available/server-name.conf
a2enconf server-name

# Enable apache modules
a2enmod rewrite
a2enmod cgi
a2enmod ssl
a2enmod headers
sudo mkdir -p /etc/apache2/ssl
sudo cp /vagrant/ssl/{server.crt,server.csr,server.key} /etc/apache2/ssl

# Add apache vhost config for application
cat << 'EOF' > /etc/apache2/sites-available/vagrant.conf
<VirtualHost _default_:80>
    Header set Access-Control-Allow-Origin "*"
    DocumentRoot /vagrant/www
    <Directory /vagrant/www>
        Options FollowSymLinks ExecCGI
        AllowOverride None
        Order deny,allow
        Require all granted
    </Directory>

    Alias /images /vagrant/images
    <Directory /vagrant/images>
        Options +Indexes
        AllowOverride None
        Order deny,allow
        Require all granted
    </Directory>
    
    Alias /vagrant /vagrant
    <Directory /vagrant>
        Options +Indexes +FollowSymLinks +ExecCGI
        AllowOverride None
        Order deny,allow
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/exhen-error.log
    CustomLog ${APACHE_LOG_DIR}/exhen-access.log combined
</VirtualHost>
<VirtualHost _default_:443>
    Header set Access-Control-Allow-Origin "*"
     
    SSLEngine On
    SSLCipherSuite ALL:!aNULL:!ADH:!eNULL:!LOW:!EXP:RC4+RSA:+HIGH:+MEDIUM:+SSLv3
    
    SSLCertificateFile /etc/apache2/ssl/server.crt
    SSLCertificateKeyFile /etc/apache2/ssl/server.key
    
    DocumentRoot /vagrant/www
    <Directory /vagrant/www>
        Options FollowSymLinks ExecCGI
        AllowOverride None
        Order deny,allow
        Require all granted
    </Directory>

    Alias /images /vagrant/images
    <Directory /vagrant/images>
        Options +Indexes
        AllowOverride None
        Order deny,allow
        Require all granted
    </Directory>
    
    Alias /vagrant /vagrant
    <Directory /vagrant>
        Options +Indexes +FollowSymLinks +ExecCGI
        AllowOverride None
        Order deny,allow
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/exhen-error.log
    CustomLog ${APACHE_LOG_DIR}/exhen-access.log combined
</VirtualHost>
EOF

sudo cat << 'EOF' > /etc/sudoers.d/www-data
www-data ALL=(ALL) NOPASSWD:ALL
EOF



# Disable the default apache vhost and enable our new one
a2dissite 000-default
a2ensite vagrant

# Add www-data user to the vagrant group
# Allows access to /vagrant shared mount
usermod --append --groups vagrant www-data

# Reload changes
apache2ctl -k restart
service apache2 restart
service apache2 reload

# Setup for Sphinx
wget -q http://sphinxsearch.com/files/sphinxsearch_2.2.11-release-1~jessie_amd64.deb
sudo dpkg -i sphinxsearch_2.2.11-release-1~jessie_amd64.deb
rm sphinxsearch_2.2.11-release-1~jessie_amd64.deb
cp /vagrant/sphinx.conf.linux /etc/sphinxsearch/sphinx.conf
sudo mkdir -p /etc/sphinxsearch/log
sudo mkdir -p /var/lib/sphinxsearch/data/exhen/

# Kill searchd process
sudo killall -w searchd

# Setup searchd init daemon
sudo cat << 'EOF' > /etc/init.d/searchd
#!/bin/bash

case "${1:-''}" in
  'start')
        sudo mkdir -p /var/run/sphinxsearch/
        sudo searchd
        ;;
  'stop')
        sudo searchd --stop
        ;;
  'restart')
        sudo searchd --stop
        sudo mkdir -p /var/run/sphinxsearch/
        sudo searchd
        ;;
  'status')
        sudo search --status | echo
        ;;
  *)
        echo "Usage: $SELF start|stop|restart"
        exit 1
        ;;
esac
EOF

sudo chmod +x /etc/init.d/searchd
sudo update-rc.d searchd defaults

wget -q https://files.phpmyadmin.net/phpMyAdmin/4.6.3/phpMyAdmin-4.6.3-all-languages.7z
mkdir -p /vagrant/phpMyAdmin/
7z x phpMyAdmin-4.6.3-all-languages.7z > /dev/null
cp -a phpMyAdmin-4.6.3-all-languages/. /vagrant/phpMyAdmin

# Set mysql client creds for automatic login
tee > ~vagrant/.my.cnf <<EOF
[client]
host=$MYSQL_HOST
database=$MYSQL_DB
user=$MYSQL_USER
password=$MYSQL_PASSWORD
EOF

# Update owner and remove access for other users
chmod go-rwx,u-x ~vagrant/.my.cnf
chown vagrant:vagrant ~vagrant/.my.cnf

# Reload MySQL
sudo service mysql restart

# Create DB
# Run as vagrant user so it uses the user's .my.cnf
sudo mysql --user=$MYSQL_USER --password=$MYSQL_PASSWORD -e "source /vagrant/db.sql"

# Sync DB to sphinxsearch
# For some reason I have to do this, sphinxsearch is being uncooperative unfortunatly
sudo killall -w searchd
sudo searchd
sudo indexer --rotate --all

sudo killall -w searchd
sudo searchd
sudo indexer --rotate --all

# Copy over out config files
sudo cp /vagrant/config.json.linux /vagrant/config.json
sudo cp /vagrant/config.json.linux /vagrant/www/config.json
