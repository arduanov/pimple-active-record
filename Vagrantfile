# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

$script = <<SCRIPT
# DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -yqq git mc;

##
# Postgresql
##
echo Installing Postgresql...

apt-get install -yqq postgresql;
sed -i "s/^local   all             postgres                                peer/local   all             postgres                                trust/" /etc/postgresql/9.4/main/pg_hba.conf |grep "^local   all             postgres                                trust" /etc/postgresql/9.4/main/pg_hba.conf
service postgresql restart;
psql -U postgres -c "ALTER USER postgres WITH ENCRYPTED PASSWORD 'dqk68MSR7iQJQJoeSU';";

##
# доступ к базе для всех по паролю
##

echo "host all  all    0.0.0.0/0  md5" >> /etc/postgresql/9.4/main/pg_hba.conf
sed -i "s/^listen_addresses='\*'//" /etc/postgresql/9.4/main/postgresql.conf
echo "listen_addresses='*'" >> /etc/postgresql/9.4/main/postgresql.conf
service postgresql restart;


echo Installing PHP...

apt-get install -yqq php5-cli php5-fpm php5-curl php5-pgsql php5-sqlite php5-mysql php5-mcrypt
#cp /vagrant/vagrant/config/php.ini /etc/php5/fpm/conf.d/
#cp /vagrant/vagrant/config/php.ini /etc/php5/cli/conf.d/

echo Installing Composer...

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

SCRIPT


Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
	config.vm.box = "debian/jessie64"

	# Create a forwarded port mapping which allows access to a specific port
	# within the machine from a port on the host machine. In the example below,
	# accessing "localhost:8080" will access port 80 on the guest machine.
	#config.vm.network :forwarded_port, guest: 8000, host: 8000

	# Create a private network, which allows host-only access to the machine
	# using a specific IP.
	config.vm.network :private_network, ip: "192.168.44.15"
	config.vm.hostname = "pimple-active-record.dev"

	# speedup filesystem
	config.vm.synced_folder "./", "/var/www/pimple-active-record", :mount_options => ['nolock,vers=3,udp,noatime,actimeo=1'], :export_options => ['async,insecure,no_subtree_check,no_acl,no_root_squash'], :nfs => true

	#config.vm.synced_folder "./", "/var/www/", owner: "vagrant", group: "www-data", mount_options: ["dmode=775,fmode=664"]

	config.vm.provider :virtualbox do |vb|
		vb.customize ["modifyvm", :id, "--memory", "1024"]
		vb.customize ["modifyvm", :id, "--cpus", "1"]
		vb.customize ["modifyvm", :id, "--hwvirtex", "on"]
		vb.customize ["modifyvm", :id, "--nestedpaging", "on"]
	end

	config.vm.provision :shell, inline: $script
end