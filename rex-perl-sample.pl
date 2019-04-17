# the following is a snippet of code used within the perl based automation tool called Rex whose home page is, https://rexify.org
# This code was used to iterate through an array of users who were allowed to have ssh accounts on servers with the wheel group permissions


desc "sshacctadd - Sync ssh accounts";  #{{{
task "sshacctadd",, sub {
    pkg "sudo",
        ensure => "present";
    #loop through array of users and add any user not present and disable accts with key of "d"
    for my $name ( keys %sshaccts ) {
        # say "user: $name - key: $sshaccts{$name}";
        # if the username contains the letter 'd' instead of a public key then disable the user
        if ( $sshaccts{$name} eq "d") {
            # delete user acct
            say "deleting $name";
            delete_user "$name", {
                force   => 1,
            };
        } else {
            say "updating $name";
            create_user "$name",
                home        => "/home/$name",
                groups      => [ 'wheel' ],
                system      => 1,
                create_home => TRUE,
                ssh_key     => "$sshaccts{$name}";
            # upload user file to /etc/sudoers.d dir
            if ($name eq "rex") {
                file "/etc/sudoers.d/$name",
                    content => "Defaults:$name !requiretty\n$name     ALL=(ALL)   NOPASSWD: ALL",
					owner   => "root",
					group   => "root";
            } else {
                say "updating sudoers.d/$name";
                file "/etc/sudoers.d/$name",
                    content => "Defaults:$name !requiretty\n$name     ALL=(ALL)   NOPASSWD: ALL",
                    #content => "$name     ALL=(ALL)   NOPASSWD: ALL";
					owner   => "root",
					group   => "root";
            }
        }
    }
    say "enabling wheel group";
    sed qr{# %wheel}, "%wheel", "/etc/sudoers"; 
	# change /etc/sudoers file owner and group to root
    say "changing /etc/sudoers file ownership to root";
	file "/etc/sudoers",
		owner => "root",
		group => "root";
};  #}}}
