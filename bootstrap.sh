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
    p7zip

# Update hostname
echo "127.0.1.1 $NEW_HOSTNAME" >> /etc/hosts
echo "$NEW_HOSTNAME" > /etc/hostname
hostname -F /etc/hostname

# Make required directories
if [ -f /vagrant/images ] || [ -f /vagrant/temp ];
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

# Set static key for this machine (whitelisted in bitbucket etc.)
# DO NOT USE THIS KEY FOR PUBLICKEY AUTHENTICATION FOR ANY SERVERS (minus this VM)
if [ -f /root/.shh ];
then
    echo ".ssh exists"
else
    mkdir /root/.ssh
    chmod 0700 /root/.ssh
    cat << 'EOF' > /root/.ssh/id_rsa
    -----BEGIN RSA PRIVATE KEY-----
    MIIEpAIBAAKCAQEAwg/mi95JSuP8aHcUEyK+Bu5Mt6Iy9DOUh1HI26UMuW08K0YI
    ZZnCXFQcK0I22vqx+mY5R0qg/29cRR4PDKk8Kmf75Vl6svUhLlA4dRoH/cJ62rjJ
    0Tk/ETblaOkQKF89qUYxTi6T1Uifi+3iuFdMB+g2R33pIFoHAr3DXIuJE/aOdKg5
    LFg04az2OeTwuvKxfwXbTNYyV9wGb5YJCC/VGVclJNhThUBbyPAc3BNA29Iu1cgV
    fjGyEi35lF6rJqOhquxXGJrPTg5HzDCrdWOmHWgqWt0+wmsrmxJYL+9d0GiqRF27
    VTHJ+KbeUVfV21PaMAGk6+ICjZmeJ8M9QAUGnwIDAQABAoIBAFpODemucgrQlueB
    6iyRcT5GbBrT9sQesJJb440afBZZl7NHbqbg60oNteIHeQFjwaiVIzhiqRLUnmpn
    d3db1WyiYNy0S921JlCn8e3ERE24z3Syou+ipQ98rTqpoeQ3lbkMuer4z8BjgCMc
    evFvZikTzRZtqCtu2W5UIfIR2KMZu8VwhBilcdMqFOtQHKKWRaGWUumPqTNkv6ih
    5HvvHZKVWqiGXOMEVC9VNN0RQGi6+Czg2zlPuV1mM2AH0u1VV6Ob7OfVlo0OpFkM
    B9NBMBAN753QOBQxYPGoiQti8pBa4/TnI1EMgPJmilOJN5uODQ5iMiwEajB8RI8n
    /m2zy6kCgYEA8cyT9spZYO6YUavS2zbAMF1TpTrChTxD9T7AgF0GfoPtWpmDnN98
    q4ugwANNmAySSr10hE/wVSuy0zWm3BYZx/K03dc8FP9LZ9G6+vnA6ZHP6RPNZRrq
    pe9PuC0EEf0Q/zVjrlmm69ngbWEkIIo75tTVAZujUTxUOFSuori265UCgYEAzXWa
    HBypoWtYmiJlqGbeVVCR8qYYuSC/ntlnNyUZmD8C/AvY09B9Sts6ZGQS8yhil/xH
    6j4SEFw/dctsDy/lrxowmlKIO/Ojy8JZaQDca1QmjrEOxtKPkEOiO+s2uj3AtiHc
    8ilNFfwgv743y4nO35WBB30mOLwRSSeQbC2NPGMCgYEAoTSgTT/Y2OwZdxHUETxu
    Y5BFDPqg500njaDZnHrosn5oRyfj/Dlvl7sOYBWTrNRs0BGBVhkphM8OeQvjBAZk
    B89DUEeIEgOmlT/ZpivOtqn08FK4dDi+ygRDpOm2Nfv/UfaZT4sL42At5R6HhH5E
    s3+fx2OpPaa4C5pBl9EIewUCgYBB6RgnLIq+XdFuoNo7y8RHWjF3xhDoUrkmHFgg
    OKadUJmEgchtKtUGzo1M502s86etWiE34/GnjfBNuZRQyuzD34L3/sH1eZNyKkbE
    iKItTDGSVPqIjcPAY/IHhs1nsafAxdw7U0SHaPqYiE0d3nefAjcCUAOS78Ib1bVe
    /r3wQQKBgQDlPvfGCqeI9bMnr27X4V8mCwhZThlehctJUEKt0hPFubALTwkuPlZs
    strPWkHTXC2s6X/zddPiQJWbLcYxHWeIIj7cj0jcxTyd3FEMtOuLzMm1/0Gt0Jqf
    bKWnVDv4AJKv0VCNp69VKlIq2Y4R/tffPyBYctpUJIa4yIcV63ZPJw==
    -----END RSA PRIVATE KEY-----
EOF

    cat << 'EOF' > /root/.ssh/id_rsa.pub
    # DO NOT USE THIS KEY FOR PUBLICKEY AUTHENTICATION FOR ANY SERVERS/SERVICES (minus this VM) YOU HAVE BEEN WARNED
    ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDCD+aL3klK4/xodxQTIr4G7ky3ojL0M5SHUcjbpQy5bTwrRghlmcJcVBwrQjba+rH6ZjlHSqD/b1xFHg8MqTwqZ/vlWXqy9SEuUDh1Ggf9wnrauMnROT8RNuVo6RAoXz2pRjFOLpPVSJ+L7eK4V0wH6DZHfekgWgcCvcNci4kT9o50qDksWDThrPY55PC68rF/BdtM1jJX3AZvlgkIL9UZVyUk2FOFQFvI8BzcE0Db0i7VyBV+MbISLfmUXqsmo6Gq7FcYms9ODkfMMKt1Y6YdaCpa3T7CayubElgv713QaKpEXbtVMcn4pt5RV9XbU9owAaTr4gKNmZ4nwz1ABQaf root@jessie64
EOF

    # Set proper perms on key files
    chmod 0600 /root/.ssh/id_rsa
    chmod 0644 /root/.ssh/id_rsa.pub

    # Bypass known-host verification for bitbucket.
    # Bypasses prompts while composer is installing packages from git repos
    cat << 'EOF' >> /etc/ssh/ssh_config
    # Bypass known-host verification for bitbucket
    Host bitbucket.org
        StrictHostKeyChecking no
        UserKnownHostsFile=/dev/null
EOF
fi


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
sudo mkdir -p /var/lib/sphinxsearch/data/exhen/
sudo mkdir -p /var/run/sphinxsearch/

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
mkdir /vagrant/phpMyAdmin/
p7zip -d phpMyAdmin-4.6.3-all-languages.7z
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

# Create DB
# Run as vagrant user so it uses the user's .my.cnf
sudo -u vagrant mysql -e "source /vagrant/db.sql" -D ""

# Sync DB to sphinxsearch
sudo indexer --rotate --all
sudo /etc/init.d/searchd restart

# Copy over out config files
sudo cp /vagrant/config.json.linux /vagrant/www/config.json