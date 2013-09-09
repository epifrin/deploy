<?php
/**
* Script for deploying web-site by ftp
* 
*/

$ini_file = 'deploy_test.ini'; // site or project ini file
ini_set("max_execution_time","120");

$arr_ini = parse_ini_file($ini_file, true);

if(!$arr_ini){
    echo 'Error occured with ini file '.$ini_file;
    exit();
}
//p($arr_ini);

$conn_id = false;
ftp_conn($arr_ini);

if(!empty($_GET['download'])){
    if(is_file($arr_ini['deployment']['local_site_dir'].$_GET['download'])){
        ftp_get($conn_id,$arr_ini['deployment']['local_site_dir'].$_GET['download'],$arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['download']),FTP_BINARY);
    }
}

$arr_local_files = get_arr_local_files($arr_ini); // get array of files

    
if($conn_id){
    
    foreach($arr_local_files AS $file=>$val){
        $ftp_file = $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/', $file);
        $ftp_fsize = ftp_size($conn_id, $ftp_file);
        if($ftp_fsize > 0){
            $arr_local_files[$file]['ftp_fsize'] = $ftp_fsize;
            $arr_local_files[$file]['ftp_fdate'] = ftp_mdtm($conn_id, $ftp_file);
            if($val['fsize'] == $ftp_fsize) $arr_local_files[$file]['equal'] = true;
        }
    }
}



// close connection
if($conn_id) ftp_close($conn_id);

?>
<table>
<tr>
    <td>ini=<?=$ini_file?></td>
</tr>
<tr>
    <td>
    <table border="1" style="border-collapse: collapse;">
    <tr>
        <th>File</th>
        <th>Date</th>
        <th>Size</th>
        <th></th>
        <th>ftp Size</th>
        <th>ftp Date</th>
    </tr>
    <?php 
    if(!empty($arr_local_files)){
        foreach($arr_local_files AS $file=>$arr_f){ ?>
        <tr>
            <td><?=$file;?></td>
            <td><?=date('H:i:s d.m.Y', $arr_f['fdate']);?></td>
            <td align="right"><?=$arr_f['fsize'];?></td>
            <td align="center"><?php if($arr_f['equal']){ echo '=';}else{echo '!=';}  ?></td>
            <td align="right"><?=$arr_f['ftp_fsize'];?></td>
            <td><?php if(!empty($arr_f['ftp_fdate'])){ echo date('H:i:s d.m.Y', $arr_f['ftp_fdate']); }?></td>
            <td><?php if(!$arr_f['equal'] && $arr_f['ftp_fsize'] > 0){ echo '<input type="button" value="Download from ftp" onclick="window.location.href=\'?download='.$file.'\'">';} ?></td>
            <td><?php if(!$arr_f['equal']){ echo '<input type="button" value="Upload to ftp">';} ?></td>
        </tr>
    <?php }
    }else{ ?>
    <tr>
        <td colspan="6">There is no local files</td>
    </tr>    
    <?php } 
    ?>
    </table>
    
    </td>
</tr>
</table>
<?php

/**
* получение массива файлов, по которым будет происходить проверка
* 
* @param array $arr_ini - array of settings
*/
function get_arr_local_files($arr_ini){
    $arr_local_files = array();
    $arr_inc_files = explode("\n", $arr_ini['deployment']['include_files']);
    $local_chdir = $arr_ini['deployment']['local_site_dir'];
    foreach($arr_inc_files AS $str){
        if(!empty($str)){
            if(strpos($str,'*') !== false){
                if($str == '*'){
                    $arr_flist = scandir($local_chdir);
                    if($arr_flist){
                        foreach($arr_flist AS $str2){
                            add_file_to_arr(&$arr_local_files, $local_chdir, $str2);
                        }
                    }
                }else{
                    if(preg_match('|(.*)\*|s',$str,$res)){
                        if(is_dir($local_chdir.$res[1])){
                            $dir = $res[1];
                            $arr_flist = scandir($local_chdir.$dir);
                            if($arr_flist){
                                foreach($arr_flist AS $str2){
                                    add_file_to_arr(&$arr_local_files, $local_chdir, $dir.$str2);
                                }
                            }
                        }
                    }
                }
                
            }else{
                add_file_to_arr(&$arr_local_files, $local_chdir, $str);
            }
        }
    }

    $arr_exc_files = explode("\n", $arr_ini['deployment']['exclude_files']);
    foreach($arr_exc_files AS $exc_file){
        if(!empty($exc_file)){
            if(is_file($local_chdir.$exc_file)){
                unset($arr_local_files[$exc_file]);
            }elseif(substr($exc_file,0,2) == '*.'){
                $ext = substr($exc_file,1);
                foreach($arr_local_files AS $file=>$val){
                    if(strpos($file, $ext) > 0){
                        unset($arr_local_files[$file]);
                    }
                }
            } 
        }
    }
    return $arr_local_files;
}

function add_file_to_arr($arr_local_files, $dir, $file){
    if(is_file($dir.$file)){
        $arr_local_files[$file] = array('fsize' => filesize($dir.$file), 'fdate' => filemtime($dir.$file), 'ftp_fsize'=>'', 'ftp_fdate'=>'', 'equal'=>false); 
    }
}

/**
* connect to ftp server
* 
* @param array $arr_ini - array of settings
*/
function ftp_conn($arr_ini){
    global $conn_id;
    if(!empty($arr_ini['ftp']['ftp_host']) && !empty($arr_ini['ftp']['ftp_user']) && !empty($arr_ini['ftp']['ftp_pass'])){
    $conn_id = ftp_connect($arr_ini['ftp']['ftp_host']);
    if($conn_id) $login_result = ftp_login($conn_id, $arr_ini['ftp']['ftp_user'], $arr_ini['ftp']['ftp_pass']);
        if ((!$conn_id) || (!$login_result)) {
            echo 'Unable to connect to FTP-server!<br>';
            echo 'Trying to connect to server '.$arr_ini['ftp']['ftp_host'].' was produced under the name of '.$arr_ini['ftp']['ftp_user'];
            exit;
        } else {
            echo 'The connection to the FTP server '.$arr_ini['ftp']['ftp_host'].' under the name of '.$arr_ini['ftp']['ftp_user'];
        }
    }
}

/**
* print variable or array
* 
* @param mixed $input
*/
function p($input){
    if(is_array($input)){
        echo '<pre>';
        print_r($input);
        echo '</pre>';
    }elseif(is_string($input) || is_numeric($input)){
        echo $input.'<br>';
    }
}

?>