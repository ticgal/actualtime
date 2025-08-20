#!/usr/bin/perl
#!/usr/bin/perl -w

use Cwd;
use File::Basename;
use File::Spec;

my $current_directory = getcwd();
my $directory_name = basename($current_directory);
my $mode = 0;

if ($directory_name eq "tools"){
    do_dir("..");
} else {
    my $ruta_subcarpeta = File::Spec->catdir($current_directory, "tools");
    if (-d $ruta_subcarpeta) {
        $mode = 1;
        do_dir(".");
    }
}

sub do_dir{
    local ($dir)=@_;
    print "Entering $dir\n";

    my @excluded_dirs=(
        ".git",
        "lib",
        "plugins",
        "vendor",
        "tests",
        "tools",
        "locales",
        "pics"
        );

    opendir(DIRHANDLE,$dir) || die "ERROR: can not read current directory\n";
    foreach (readdir(DIRHANDLE)){
        if ($_ ne '..' && $_ ne '.'){
            # Excluded directories
            if ( $_ ~~ @excluded_dirs ){
                print "Skipping $dir/$_\n";
                next;
            }
            if (-d "$dir/$_"){
                do_dir("$dir/$_");
            } else {
                if (!(-l "$dir/$_")){
                    # Included filetypes - php, css, js => default comment style
                    if ((index($_,".php",0)!=-1)||(index($_,".css",0)!=-1)||(index($_,".js",0)!=-1)){
                        do_file("$dir/$_", "", " * ");
                    }
                    # Included filetypes - twig => ({# #})
                    if ((index($_,".twig",0)!=-1)){
                        do_file("$dir/$_", "", " # ");
                    }
                    # Included filetypes - sql, sh, pl => Add a specific comment style (#)
                    if ((index($_,".sql",0)!=-1)||(index($_,".sh",0)!=-1)||(index($_,".pl",0)!=-1)){
                        do_file("$dir/$_", "", "# ");
                    }
                }
            }
        }
    }
    closedir DIRHANDLE;
}

sub do_file{
    local ($file, $format, $decor)=@_;
    if ($format ne "") {
        print "Replacing header on $file. (Using specific comment $format)\n";
    } else {
        print "Replacing header on $file.\n"
    }

    ### DELETE HEADERS
    open(INIT_FILE,$file);
    @lines=<INIT_FILE>;
    close(INIT_FILE);

    open(TMP_FILE,">/tmp/tmp_glpi.txt");

    $status='';
    foreach (@lines){
        # Did we found header closure tag ?
        if ($_ =~ m/$format\*\// || $_ =~ m/$format\#\}/){
            # if line starts with */ or #} add a space before to fix comment style
            if ($_ =~ m/$format\*\//){
                $_ =~ s/^$format\*\// $format\*\//;
            } elsif ($_ =~ m/$format\#\}/){
                $_ =~ s/^$format\#\}/ $format\#\}/;
            }
            $status="END";
        }

        # If we have reach the header closure tag, we print the rest of the file
        if ($status =~ m/END/||$status !~ m/BEGIN/){
            print TMP_FILE $_;
        }

        # If we haven't reach the header closure tag
        if ($status !~ m/END/){
            # If we found the header open tag...
            if (
                ($_ =~ m/$format\/\*\*/ || $_ =~ m/$format\/\*/)
                || ($_ =~ m/$format\{\#/)
            ){
                # if line is /* replace by /**
                if ($_ =~ m/$format\/\*/){
                    $_ =~ s/$format\/\*/$format\*\*/;
                }

                $status="BEGIN";
                ##### ADD NEW HEADERS
                #print "Replacing header on $file.\n";

                if ($mode == 0) {
                    open(HEADER_FILE,"TICGAL_HEADER");
                } else {
                    open(HEADER_FILE,"tools/TICGAL_HEADER");
                }

                @headers=<HEADER_FILE>;
                foreach (@headers){
                    print TMP_FILE $decor;
                    print TMP_FILE $_;
                }
                close(HEADER_FILE) ;
            }
        }
    }
    close(TMP_FILE);
    system("cp -f /tmp/tmp_glpi.txt $file");

    # If we haven't found an header on the file, report it
    if ($status eq '') {
        print "Unable to found an header on $file. Please add it manually";
        #exit 1;
    }
}