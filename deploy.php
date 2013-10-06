<?php
/**
* Script for deploying web-sites to ftp server
* 
*/
ini_set("max_execution_time","120");
session_start();      
setlocale(LC_ALL, 'ru_RU');

$main_ini_file = 'deploy.ini'; // main ini file

$arr_ini = ''; $ini_file = ''; $arr_ini_list = '';
check_ini_file($main_ini_file);


$remote_site_dir = '';
if(!empty($arr_ini['local-server']['remote_site_dir'])){
    if(is_dir($arr_ini['local-server']['remote_site_dir'])){
        $remote_site_dir = $arr_ini['local-server']['remote_site_dir'];
    }else{
        exit('Remote directory does not exist');
    }
}

$conn_id = false;
if(!$remote_site_dir){
    ftp_conn($arr_ini['ftp']);
    if(!$conn_id) exit('Unable to connect to FTP-server!'); 
} 



// checkbox "Show only not equal files"
if(isset($_GET['show_only_not_equal'])){
    if(!empty($_GET['show_only_not_equal'])){
        setcookie('deploy_show_only_not_equal', 1, time()+2592000, '/');
    }else{
        setcookie('deploy_show_only_not_equal', '', time()-1, '/');
    }
    header('Location: deploy.php');
    exit();
}
$show_only_no_equal = false;
if(!empty($_COOKIE['deploy_show_only_not_equal'])){
    $show_only_no_equal = true; 
}

if(!empty($_GET['rescan'])){
    $_SESSION['arr_local_files'] = '';
    header('Location: deploy.php');
    exit();
}

if(!empty($_GET['clear_log'])){
    $_SESSION['logs'] = '';
    header('Location: deploy.php');
    exit();
}




// multiple download or upload
if(!empty($_POST['atype'])){
    if(is_array($_POST['files']) && count($_POST['files']) > 0){
        foreach($_POST['files'] AS $file){
            if(is_file($arr_ini['deployment']['local_site_dir'].$file)){
                if($_POST['atype'] == 'download'){
                    if($remote_site_dir){
                        $action_result = copy($remote_site_dir.$file, $arr_ini['deployment']['local_site_dir'].$file);
                    }else{
                        ob_start();
                        $action_result = @ftp_get($conn_id, "php://output", $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$file), FTP_BINARY);
                        if($action_result) file_put_contents($arr_ini['deployment']['local_site_dir'].$file, ob_get_contents());
                        ob_end_clean();
                    }
                    if( $action_result ){
                        to_log('File <b>'.$file.'</b> was successfully downloaded');    
                    }else{
                        to_log('Unable to download file <b>'.$file.'</b>');
                    }
                    
                }elseif($_POST['atype'] == 'upload'){
                    
                    if($remote_site_dir){
                        $action_result = copy($arr_ini['deployment']['local_site_dir'].$file, $remote_site_dir.$file);
                    }else{
                        $action_result = ftp_put($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$file), $arr_ini['deployment']['local_site_dir'].$file, FTP_BINARY);
                    }
                    if( $action_result ){
                        to_log('File <b>'.$file.'</b> was successfully uploaded');
                    }else{
                        to_log('Unable to upload file <b>'.$file.'</b>');
                    }
                }
            }
        }
        $_SESSION['arr_local_files'] = '';
        header('Location: deploy.php');
        exit();
    }
}

// single download
if(!empty($_GET['download'])){
    if(is_file($arr_ini['deployment']['local_site_dir'].$_GET['download'])){
        if($remote_site_dir){
            $action_result = copy($remote_site_dir.$_GET['download'], $arr_ini['deployment']['local_site_dir'].$_GET['download']);
        }else{
            ob_start();
            $action_result = @ftp_get($conn_id, "php://output", $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['download']), FTP_BINARY);
            if($action_result) file_put_contents($arr_ini['deployment']['local_site_dir'].$_GET['download'], ob_get_contents());
            ob_end_clean();
        }
        if( $action_result ){
            to_log('File <b>'.$_GET['download'].'</b> was successfully downloaded');
            $_SESSION['arr_local_files'] = '';
            header('Location: deploy.php');
            exit(); 
        }else{
            to_log('Failed to download the file <b>'.$_GET['download'].'</b>', true);
        }
    }
}

// single upload
if(!empty($_GET['upload'])){
    if(is_file($arr_ini['deployment']['local_site_dir'].$_GET['upload'])){
        if($remote_site_dir){
            $action_result = copy($arr_ini['deployment']['local_site_dir'].$_GET['upload'], $remote_site_dir.$_GET['upload']);
        }else{
            $action_result = ftp_put($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['upload']), $arr_ini['deployment']['local_site_dir'].$_GET['upload'], FTP_BINARY);
        }
        if( $action_result ){
            to_log('File <b>'.$_GET['upload'].'</b> was successfully uploaded');
            $_SESSION['arr_local_files'] = '';
            header('Location: deploy.php');
            exit(); 
        }else{
            to_log('Failed to upload the file <b>'.$_GET['upload'].'</b>', true);
        }
    }
}

// delete from ftp
if(!empty($_GET['delete'])){
    if($remote_site_dir){
        $action_result = unlink($remote_site_dir.$_GET['delete']);
    }else{
        if(ftp_size($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['delete'])) > -1){
            $action_result = ftp_delete($conn_id, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$_GET['delete']));
        }else{
            $action_result = false;
        }
    }
    
    if( $action_result ){
        to_log('File <b>'.$_GET['delete'].'</b> was successfully deleted');
        $_SESSION['arr_local_files'] = '';
        header('Location: deploy.php');
        exit();    
    }else{
        to_log('Failed to delete the file <b>'.$_GET['delete'].'</b>', true);
    }
}

// compare files
if(!empty($_GET['compare'])){
    if(file_exists($arr_ini['deployment']['local_site_dir'].$_GET['compare'])){
        $local_file = $_GET['compare'];
        $local_content = file_get_contents($arr_ini['deployment']['local_site_dir'].$_GET['compare']);
         
        
        $remote_content = '';
        if($remote_site_dir){
            $remote_content = file_get_contents($remote_site_dir.$_GET['compare']);
        }else{
            ob_start();
            if(@ftp_get($conn_id, "php://output", $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$local_file), FTP_BINARY)){
                $remote_content = ob_get_contents();
                ob_end_clean();
            }else{
                ob_end_clean();
                $error = 'Failed to download file '.$local_file;
            }
        }
        
        if(!empty($arr_ini['common']['charset']) && strtoupper($arr_ini['common']['charset']) != 'UTF-8'){
            $local_content = iconv($arr_ini['common']['charset'], 'UTF-8//IGNORE', $local_content);
            $remote_content = iconv($arr_ini['common']['charset'], 'UTF-8//IGNORE', $remote_content);
        }
        
        if($remote_content){
            
            
            //@ftp_get($conn_id, $ftp_temp_file, $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/',$local_file), FTP_BINARY);
            //if(file_exists($ftp_temp_file)){
                 //$remote_content = file_get_contents($ftp_temp_file);
                 
                 
                 /*
                 $arr_local_content = explode("\n", $local_content);
                 $arr_remote_content = explode("\n", $remote_content);
                 
                 $i = 0; $arr_compare = array();
                 foreach($arr_local_content AS $val){
                     $arr_compare[$i] = 0;
                     if($arr_local_content[$i] != $arr_remote_content[$i]) $arr_compare[$i+1] = 1;
                     $i++;
                 }
                 */
            //}else{
            //     p('Temporary file '.$ftp_temp_file.' does not exist');
            //}
        }
    }
}


$arr_local_files = get_arr_local_files($arr_ini); // get array of files

    



    
if(empty($_SESSION['arr_local_files']) || !is_array($_SESSION['arr_local_files'])){
    if($remote_site_dir){
        foreach($arr_local_files AS $file=>$val){
            if(file_exists($remote_site_dir.$file)){
                $remote_file_size = filesize($remote_site_dir.$file);
                if($remote_file_size !== false){
                    $arr_local_files[$file]['ftp_fsize'] = $remote_file_size;
                    $arr_local_files[$file]['ftp_fdate'] = date('H:i:s d.m.Y', filemtime($remote_site_dir.$file));
                    if($val['fsize'] == $remote_file_size) $arr_local_files[$file]['equal'] = true;
                }
            }
        }
    }else{
        
        if($conn_id){
            foreach($arr_local_files AS $file=>$val){
                $ftp_file = $arr_ini['ftp']['ftp_remote_dir'].str_replace('\\','/', $file);
                $ftp_fsize = ftp_size($conn_id, $ftp_file);
                if($ftp_fsize > -1){
                    $arr_local_files[$file]['ftp_fsize'] = $ftp_fsize;
                    $arr_local_files[$file]['ftp_fdate'] = gmdate('H:i:s d.m.Y',ftp_mdtm($conn_id, $ftp_file));//strftime('%H:%M:%S %d.%m.%Y', ftp_mdtm($conn_id, $ftp_file));
                    if($val['fsize'] == $ftp_fsize) $arr_local_files[$file]['equal'] = true;
                }
            }
            $_SESSION['arr_local_files'] = $arr_local_files;
        }
    }
}



// close connection
if($conn_id) ftp_close($conn_id);

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
    <title>Deploy</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <?php get_css(); ?>
</head>
<body>

<?php
if(!empty($_GET['compare']) && isset($local_file)){
?>
<div>
    <p><b><?=$local_file?></b></p>
    <div>
        <table style="width:1200px;" class="t_compare">
        <tr>
            <td valign="top"><div style="width:595px;overflow-x:scroll;"><pre><?=htmlspecialchars($local_content);?></pre></div></td> 
            <td class="light_gray" style="width:auto;">&nbsp;</td>
            <td valign="top"><div style="width:595px;overflow-x:scroll;">
                <?php if(!empty($error)) echo '<p class="err">'.$error.'</p>'; ?>
                <pre><?=htmlspecialchars($remote_content);?></pre></div>
            </td>
        </tr>
        </table>
    </div>
</div>

<?php        
}else{
?>


<table>
<tr>
    <td>
        <table width="100%">
        <tr>
            <td>ini=<?=$ini_file?></td>
            <td align="right"><form action="" name="form_change_ini" style="margin: 0;">
                <select name="ini" onchange="document.form_change_ini.submit();">
                <?php foreach($arr_ini_list AS $site_name=>$ini_f){ ?>
                    <option value="<?=$site_name?>" <?php if($ini_f == $ini_file){ ?>selected="selected"<?php } ?>><?=$site_name?></option>
                <?php } ?>
                </select>
            </form></td>
        </tr>
        <tr>
            <td><input type="button" value="rescan" onclick="btn_rescan();"></td>
            <td align="right"><label><input type="checkbox" id="show_only_not_equal" name="show_only_not_equal" value="1" <?php if($show_only_no_equal){ ?> checked="checked"<?php } ?> onchange="change_show_only_not_equal();"> Show only not equal files</label></td>
        </tr>
        </table>
    </td>
    <td>&nbsp;</td>
    <td valign="bottom"><input type="button" value="clear log" onclick="btn_clear_log();"></td>
</tr>
<tr>
    <td valign="top">
        <form action="" name="form_file_list" method="post">
        <input type="hidden" name="atype" value="download">
        <table border="1" cellpadding="2" cellspacing="2" class="t_filelist">
        <tr>
            <th colspan="4">Local computer</th>
            <th></th>
            <th colspan="2" class="light_gray">Remote Server</th>
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
            foreach($arr_local_files AS $file=>$arr_f){ 
            if(!$show_only_no_equal || ($show_only_no_equal && !$arr_f['equal'])){ ?>
            <tr <?php if(!$arr_f['equal']){ ?>class="tr_bg_red" <?php } ?> >
                <td><input type="checkbox" name="files[]" value="<?=$file;?>" <?php if(!$arr_f['equal']){ ?> checked="checked" <?php } ?> ></td>
                <td><a href="?compare=<?=urlencode($file);?>"><?=$file;?></a></td>
                <td><?=$arr_f['fdate']?></td>
                <td align="right"><?=$arr_f['fsize'];?></td>
                <td align="center"><?php if($arr_f['equal']){ echo '=';}else{echo '<span class="red">!=</span>';}  ?></td>
                <td align="right" class="light_gray"><?=$arr_f['ftp_fsize'];?></td>
                <td class="light_gray"><?php if(!empty($arr_f['ftp_fdate'])){ echo $arr_f['ftp_fdate']; }?></td>
                <td><?php if(!$arr_f['equal'] && $arr_f['ftp_fsize'] > 0){ ?> 
                    <input type="button" value="Download" class="btn" onclick="window.location.href='?download=<?=urlencode($file);?>'"> 
                    <?php } ?>
                </td>
                <td><?php if(!$arr_f['equal']){ ?> 
                    <input type="button" value="Upload" class="btn" onclick="window.location.href='?upload=<?=urlencode($file);?>'"> 
                    <?php } ?>
                </td>
                <td><?php if($arr_f['ftp_fsize'] > 0){ ?> 
                    <input type="button" value="Delete" class="btn" onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?=urlencode($file);?>'">
                    <?php } ?>
                </td>
            </tr>
        <?php }}
        }else{ ?>
        <tr>
            <td colspan="6">There is no local files</td>
        </tr>    
        <?php } 
        ?>
        </table>
        <p><label><input type="checkbox" id="checked_all" onclick="checkuncheck();" checked="checked"> All checked/unchecked</label></p>
        <p><input type="button" value="Download all checked" class="btn" onclick="btn_click('download');"> 
            <input type="button" value="Upload all checked" class="btn" onclick="btn_click('upload');"></p>
        </form>
    </td>
    <td>&nbsp;</td>
    <td class="tab_log" valign="top">
        <?php if(!empty($_SESSION['logs'])) echo $_SESSION['logs']; ?>
    </td>
</tr>
</table>
<?php } ?>
<script>
function btn_click(atype){
    document.form_file_list['atype'].value = atype;
    document.form_file_list.submit();
}
function change_show_only_not_equal(){
    var el_show_only = document.getElementById('show_only_not_equal');
    if(el_show_only.checked){
        window.location.href = 'deploy.php?show_only_not_equal=1';
    }else{
        window.location.href = 'deploy.php?show_only_not_equal=0';
    }
}
function btn_rescan(){
    window.location.href = 'deploy.php?rescan=1';
}
function btn_clear_log(){
    window.location.href = 'deploy.php?clear_log=1';
}
function checkuncheck(){
    var el_checked = document.getElementById('checked_all');
    var form_file_list = document.form_file_list;
    if(el_checked.checked){
        for(var i = 0; i < form_file_list.elements.length; i++){ 
            el = form_file_list.elements[i]; 
            if(el.type == 'checkbox'){
                el.checked = 'checked';
            }
        }
    }else{
        for(var i = 0; i < form_file_list.elements.length; i++){ 
            el = form_file_list.elements[i]; 
            if(el.type == 'checkbox'){
                el.checked = false;
            }
        }
    }
}
</script>
</body>
</html>
<?php       

############# Functions ##############

function check_ini_file($main_ini_file){
    global $arr_ini, $ini_file, $arr_ini_list;
    if(!is_file($main_ini_file)) exit('Ini-file <b>'.$main_ini_file.'</b> not found');
    
    $arr_main_ini = parse_ini_file($main_ini_file, true);
    if(!empty($arr_main_ini['sites'])){
        $arr_ini_list = array();
        foreach($arr_main_ini['sites'] AS $site_name => $ini_f) if(is_file($ini_f)) $arr_ini_list[$site_name] = $ini_f;
        if(count($arr_ini_list) > 0) $ini_file = reset($arr_ini_list);
    }

    if(!empty($_GET['ini'])){
        $_SESSION['arr_local_files'] = '';
        if(!empty($arr_ini_list[$_GET['ini']])){ 
            $ini_file = $arr_ini_list[$_GET['ini']];
            setcookie('deploy_ini_name', $_GET['ini'], time()+2592000, '/');
            to_log('Ini-file changed on <b>'.$ini_file.'</b>');
            header('Location: deploy.php');
            exit();
        }
    }elseif(!empty($_COOKIE['deploy_ini_name'])){
        if(!empty($arr_ini_list[$_COOKIE['deploy_ini_name']])){  
            $ini_file = $arr_ini_list[$_COOKIE['deploy_ini_name']];
        }else{
            setcookie('deploy_ini_name', '', time()-1, '/');
        }
    }

    if(!is_file($ini_file)) exit('Ini-file <b>'.$ini_file.'</b> not found');
    $arr_ini = parse_ini_file($ini_file, true);

    if(!$arr_ini){
        exit('Error occured with ini file '.$ini_file);
    }

    if(!empty($arr_ini['security']['allow_ip']) && strpos($arr_ini['security']['allow_ip'], $_SERVER['REMOTE_ADDR']) === false ){
        exit('Permission denied');
    }
    
    if(empty($arr_ini['deployment']['local_site_dir'])){
        exit('Ini file does not contain parameter [local_site_dir] in section [deployment]');
    }
    
    if(substr($arr_ini['deployment']['local_site_dir'],-1,1) != DIRECTORY_SEPARATOR) $arr_ini['deployment']['local_site_dir'] .= DIRECTORY_SEPARATOR;
    if(!is_dir($arr_ini['deployment']['local_site_dir'])){
        exit('Directory [local_site_dir] does not exist!');
    }
}

/**
* getting array of local files
* 
* @param array $arr_ini - array of settings
*/
function get_arr_local_files($arr_ini){
    $arr_local_files = array();
    
    if(!empty($_SESSION['arr_local_files']) && is_array($_SESSION['arr_local_files'])) return $_SESSION['arr_local_files'];
    // include files
    $arr_inc_files = explode("\n", $arr_ini['deployment']['include_files']);
    $local_chdir = $arr_ini['deployment']['local_site_dir'];
    foreach($arr_inc_files AS $str){
        $str = trim($str);
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
        $exc_file = trim($exc_file);
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
            'fdate' => date('H:i:s d.m.Y', filemtime($arr_ini['deployment']['local_site_dir'].$dir.$file)), 
            'ftp_fsize'=>'', 
            'ftp_fdate'=>'', 
            'equal'=>false); 
}

/**
* connect to ftp server
* 
* @param array $arr_ini - array of settings
*/
function ftp_conn($arr_ini_ftp){
    global $conn_id;

    if(!$conn_id){
        if(!empty($arr_ini_ftp['ftp_host']) && !empty($arr_ini_ftp['ftp_user']) && !empty($arr_ini_ftp['ftp_pass'])){
        $conn_id = ftp_connect($arr_ini_ftp['ftp_host'], $arr_ini_ftp['ftp_port'], 30);
        if($conn_id) $login_result = ftp_login($conn_id, $arr_ini_ftp['ftp_user'], $arr_ini_ftp['ftp_pass']);
            if ( $conn_id && $login_result ){
                ftp_pasv($conn_id, true);
                $_SESSION['conn_id'] = $conn_id;
                //to_log('The connection to the FTP server <b>'.$arr_ini['ftp']['ftp_host'].'</b> under the name of <b>'.$arr_ini['ftp']['ftp_user'].'</b>');
                
            } else {
                to_log('Unable to connect to FTP-server!', true);
                to_log('Trying to connect to server <b>'.$arr_ini_ftp['ftp_host'].'</b> was produced under the name of '.$arr_ini_ftp['ftp_user'].'</b>', true);
            }
        }
    }
}

/**
* output css styles
* 
*/
function get_css(){
?>
<style>
    body { 
        font-family: Verdana, Arial;
        font-size: 11px;
        color: #333;
    }
    td {
        font-size: 12px;
    }
    .btn {
        font-family: Verdana, Arial;
        font-size: 10px;
    }
    .t_filelist {
        border-collapse: collapse;
        border: 1px solid #CCC;
    }
    .t_filelist th {
        border: 1px solid #CCC;
        color:#555;
        font-size: 11px;
    }
    .t_filelist td {
        border: 1px solid #CCC;
        font-size: 11px;
    }
    .tr_bg_red {
        background-color: #FFDDDE;
    }
    .t_compare {
        border-collapse: collapse;
    }
    .t_compare td {
        border: 1px solid #CCC;
    }
    .light_gray {
        background-color:#EFEFEF;
    }
    .red, .err {color:red;}
    .tab_log {
        font-family: monospace;
        font-size: 12px;
    }
</style>
<?php
}

function to_log($str, $bool_err = false){
    if(empty($_SESSION['logs'])) $_SESSION['logs'] = '';
    $err_class = '';  if($bool_err) $err_class = ' class="err"';
    $_SESSION['logs'] .= '<span'.$err_class.'>'.date('H:i:s').': '.$str.'<br></span>';
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