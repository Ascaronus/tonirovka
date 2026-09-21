<?php
session_start();$_SESSION['admin_logged_in']=true;session_write_close();$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/admin/settings.php';
ob_start();require __DIR__.'/../admin/settings.php';ob_end_clean();
$pdo=getDBConnection();$original=$pdo->query('SELECT * FROM prices ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$dir=DATA_DIR.'/backups';if(!is_dir($dir))mkdir($dir,0755,true);
$file=$dir.'/audit-invalid.json';
try {
    $bad=$original;$bad[]=$original[0];file_put_contents($file,json_encode(['prices'=>$bad]));
    if(restoreFromBackup('audit-invalid.json')!==false)throw new RuntimeException('Invalid restore accepted');
    if($pdo->query('SELECT * FROM prices ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)!==$original)throw new RuntimeException('Rollback lost rows');
    if(deleteBackup('../contact-clicks.json')!==false)throw new RuntimeException('Backup path traversal');
    file_put_contents($file,json_encode(['prices'=>[['id` DROP'=>'bad']]]));
    if(restoreFromBackup('audit-invalid.json')!==false)throw new RuntimeException('Unknown column accepted');
    if($pdo->query('SELECT * FROM prices ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)!==$original)throw new RuntimeException('Validation damaged rows');
}finally{if(is_file($file))unlink($file);}
echo "Backup restore: rollback and invalid path/column rejection passed\n";
