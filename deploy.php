<?php
/**
* Script for deploying web-site by ftp
* 
*/
ini_set("max_execution_time","120");

$main_ini_file = 'deploy.ini'; // site or project ini file

if(!is_file($main_ini_file)) exit('Ini-file <b>'.$main_ini_file.'</b> not found');
$arr_main_ini = parse_ini_file($main_ini_file, true);
if(!empty($arr_main_ini['sites'])){
    $arr_ini_list = array();
    foreach($arr_main_ini['sites'] AS $site_name => $ini_f) if(is_file($ini_f)) $arr_ini_list[$site_name] = $ini_f;
    if(count($arr_ini_list) > 0) $ini_file = reset($arr_ini_list);
}

if(!empty($_GET['ini'])){
    if(!empty($arr_ini_list[$_GET['ini']])){ 
        $ini_file = $arr_ini_list[$_GET['ini']];
        setcookie('ini_name', $_GET['ini'], time()+2592000, '/');
        header('Location: deploy.php');
        exit();
    }
}elseif(!empty($_COOKIE['ini_name'])){
    if(!empty($arr_ini_list[$_COOKIE['ini_name']])){  
        $ini_file = $arr_ini_list[$_COOKIE['ini_name']];
    }else{
        setcookie('ini_name', '', time()-1, '/');
    }
}

if(!is_file($ini_file)) exit('Ini-file <b>'.$ini_file.'</b> not found');
$arr_ini = parse_ini_file($ini_file, true);

if(!$arr_ini){
    echo 'Error occured with ini file '.$ini_file;
    exit();
}
//p($arr_ini);

if(!empty($arr_ini['security']['allow_ip']) && strpos($arr_ini['security']['allow_ip'], $_SERVER['REMOTE_ADDR']) === false ){
    echo 'Permission denied';
    exit();
}

$conn_id = false;
ftp_conn($arr_ini);




if($conn_id){
    // multiple download or upload
    if(!empty($_POST['atype'])){
        if(is_array($_POST['files']) && count($_POST['files']) > 0){
            foreach($_POST['files'] AS $file){
                if(is_file($arr_ini['deployment']['local_site_dir'].$file)){
                    if($_POST['atype'] == 'download'){
                        ftp_get($conn_id,$arr_ini['deployment']['local_site_dir'].$file, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$file), FTP_BINARY);
                    }elseif($_POST['atype'] == 'upload'){
                        ftp_put($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$file), $arr_ini['deployment']['local_site_dir'].$file, FTP_BINARY);
                    }
                }
            }
            header('Location: deploy.php');
            exit();
        }
    }
    
    // single download
    if(!empty($_GET['download'])){
        if(is_file($arr_ini['deployment']['local_site_dir'].$_GET['download'])){
            if( ftp_get($conn_id,$arr_ini['deployment']['local_site_dir'].$_GET['download'],$arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['download']),FTP_BINARY)){
                header('Location: deploy.php');
                exit(); 
            }else{
                echo 'Failed to download the file '.$_GET['download'];
            }
        }
    }

    // single upload
    if(!empty($_GET['upload'])){
        if(is_file($arr_ini['deployment']['local_site_dir'].$_GET['upload'])){
            if( ftp_put($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['upload']), $arr_ini['deployment']['local_site_dir'].$_GET['upload'], FTP_BINARY) ){
                header('Location: deploy.php');
                exit(); 
            }else{
                echo 'Failed to upload the file '.$_GET['upload'];
            }
        }
    }
    
    // delete from ftp
    if(!empty($_GET['delete'])){
        if(ftp_size($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['delete'])) > -1){
            ftp_delete($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['delete']));
            header('Location: deploy.php');
            exit();
        }
    }
}

$arr_local_files = get_arr_local_files($arr_ini); // get array of files

    
if($conn_id){
    
    foreach($arr_local_files AS $file=>$val){
        $ftp_file = $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/', $file);
        $ftp_fsize = ftp_size($conn_id, $ftp_file);
        if($ftp_fsize > -1){
            $arr_local_files[$file]['ftp_fsize'] = $ftp_fsize;
            $arr_local_files[$file]['ftp_fdate'] = ftp_mdtm($conn_id, $ftp_file);
            if($val['fsize'] == $ftp_fsize) $arr_local_files[$file]['equal'] = true;
        }
    }
}



// close connection
if($conn_id) ftp_close($conn_id);

?>
<html>
<head>
    <title>Deploy</title>
</head>
<body>
<style>
    body { 
        color: #333;
    }
    .t_filelist {
        border-collapse: collapse;
        border: 1px solid #CCC;
    }
    .t_filelist th {
        border: 1px solid #CCC;
        color:#555;
    }
    .t_filelist td {
        border: 1px solid #CCC;
    }
    .light_gray {
        background-color:#EFEFEF;
    }
    .red {color:red;}
</style>
<table>
<tr>
    <td>ini=<?=$ini_file?>
    <form action="" name="form_change_ini">
        <select name="ini" onchange="document.form_change_ini.submit();">
        <?php foreach($arr_ini_list AS $site_name=>$ini_f){ ?>
            <option value="<?=$site_name?>" <?php if($ini_f == $ini_file){ ?>selected="selected"<?php } ?>><?=$site_name?></option>
        <?php } ?>
        </select>
    </form>
    </td>
</tr>
<tr>
    <td>
    <form action="" name="form_file_list" method="post">
    <input type="hidden" name="atype" value="download">
    <table border="1" cellpadding="2" cellspacing="2" class="t_filelist">
    <tr>
        <th colspan="4">Local computer</th>
        <th></th>
        <th colspan="2" class="light_gray">FTP Server</th>
        <th colspan="3">Actions</th>
    </tr>
    <tr>
        <th></th>
        <th>File</th>
        <th>Date</th>
        <th>Size, byte</th>
        <th></th>
        <th class="light_gray">Size, byte</th>
        <th class="light_gray">Date</th>
        <th>From FTP</th>
        <th>To FTP</th>
        <th>From FTP</th>
    </tr>
    <?php 
    if(!empty($arr_local_files)){
        foreach($arr_local_files AS $file=>$arr_f){ ?>
        <tr>
            <td><input type="checkbox" name="files[]" value="<?=$file;?>" <?php if(!$arr_f['equal']){ ?> checked="checked" <?php } ?> ></td>
            <td><?=$file;?></td>
            <td><?=date('H:i:s d.m.Y', $arr_f['fdate']);?></td>
            <td align="right"><?=$arr_f['fsize'];?></td>
            <td align="center"><?php if($arr_f['equal']){ echo '=';}else{echo '<span class="red">!=</span>';}  ?></td>
            <td align="right" class="light_gray"><?=$arr_f['ftp_fsize'];?></td>
            <td class="light_gray"><?php if(!empty($arr_f['ftp_fdate'])){ echo date('H:i:s d.m.Y', $arr_f['ftp_fdate']); }?></td>
            <td><?php if(!$arr_f['equal'] && $arr_f['ftp_fsize'] > 0){ ?> 
                <input type="button" value="Download" onclick="window.location.href='?download=<?=urlencode($file);?>'"> 
                <?php } ?>
            </td>
            <td><?php if(!$arr_f['equal'] && $conn_id){ ?> 
                <input type="button" value="Upload" onclick="window.location.href='?upload=<?=urlencode($file);?>'"> 
                <?php } ?>
            </td>
            <td><?php if($conn_id){ ?> 
                <input type="button" value="Delete" onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?=urlencode($file);?>'"> 
                <?php } ?>
            </td>
        </tr>
    <?php }
    }else{ ?>
    <tr>
        <td colspan="6">There is no local files</td>
    </tr>    
    <?php } 
    ?>
    </table>
    <p><input type="button" value="Download all checked" onclick="btn_click('download');"> <input type="button" value="Upload all checked" onclick="btn_click('upload');"></p>
    </form>
    </td>
</tr>
</table>
<script>
function btn_click(atype){
    document.form_file_list['atype'].value = atype;
    document.form_file_list.submit();
}
</script>
</body>
</html>
<?php

/**
* получение массива файлов, по которым будет происходить проверка
* 
* @param array $arr_ini - array of settings
*/
function get_arr_local_files($arr_ini){
    $arr_local_files = array();
    // include files
    $arr_inc_files = explode("\n", $arr_ini['deployment']['include_files']);
    $local_chdir = $arr_ini['deployment']['local_site_dir'];
    foreach($arr_inc_files AS $str){
        if(!empty($str)){
            if(strpos($str,'*') !== false){
                if($str == '*'){
                    get_files_tree(&$arr_local_files, '');
                    break;
                }else{
                    if(preg_match("|^(.+)\\*$|s", $str, $res)){
                        if(is_dir($local_chdir.$res[1])){  
                            get_files_tree(&$arr_local_files, $res[1]);
                        }
                    }
                }
                
            }elseif(is_file($local_chdir.$str)){
                add_file_to_arr(&$arr_local_files, '', $str);
            }
        }
    }

    // exclude files
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
            }elseif(substr($exc_file, -2) == DIRECTORY_SEPARATOR.'*'){
                $ext_folder = substr($exc_file, 0, -1);
                $ext_len = strlen($ext_folder);
                foreach($arr_local_files AS $file=>$val){
                    if(substr($file,0,$ext_len) == $ext_folder){
                        unset($arr_local_files[$file]);
                    }
                }
            } 
        }
    }
    return $arr_local_files;
}

function get_files_tree($arr_local_files, $dir){
    global $arr_ini;
    $local_chdir = $arr_ini['deployment']['local_site_dir'];  
    if(is_dir($local_chdir.$dir)){    
        $arr_list = scandir($local_chdir.$dir);
        foreach($arr_list AS $val){    
            if(is_file($local_chdir.$dir.$val)){
                add_file_to_arr(&$arr_local_files, $dir, $val);
            }
            elseif($val != '.' && $val != '..' && is_dir($local_chdir.$dir.$val)){  
                
                get_files_tree(&$arr_local_files, $dir.$val.DIRECTORY_SEPARATOR);
            }
        }
    }
    return true;
}

function add_file_to_arr($arr_local_files, $dir, $file){
    global $arr_ini;
    $arr_local_files[$dir.$file] = array('fsize' => filesize($arr_ini['deployment']['local_site_dir'].$dir.$file), 
            'fdate' => filemtime($arr_ini['deployment']['local_site_dir'].$dir.$file), 
            'ftp_fsize'=>'', 
            'ftp_fdate'=>'', 
            'equal'=>false); 
}

/**
* connect to ftp server
* 
* @param array $arr_ini - array of settings
*/
function ftp_conn($arr_ini){
    global $conn_id;
    if(!empty($arr_ini['ftp']['ftp_host']) && !empty($arr_ini['ftp']['ftp_user']) && !empty($arr_ini['ftp']['ftp_pass'])){
    $conn_id = ftp_connect($arr_ini['ftp']['ftp_host'], $arr_ini['ftp']['ftp_port']);
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
    if(is_array($input) || is_object($input)){
        echo '<pre>';
        print_r($input);
        echo '</pre>';
    }elseif(is_string($input) || is_numeric($input)){
        echo $input.'<br>';
    }
}

?>