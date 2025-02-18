<?php
$root = $_SERVER['DOCUMENT_ROOT'];
$envFilepath = "$root/sbfutils-printer/.env";

if (is_file($envFilepath)) {
    $file = new \SplFileObject($envFilepath);
    while (false === $file->eof()) {
        putenv(trim($file->fgets()));
    }
}

$dataPrinter = getenv('PRINTER', 'default value');

//$file = $_GET["data"];
$url = $_GET["data"];
$file_name = basename($url);
$tmpdir = sys_get_temp_dir();
$file =  tempnam($tmpdir, 'ctk');

if (file_put_contents($file, file_get_contents($url))){
    echo "File downloaded successfully";
}else{
    echo "File downloading failed.";
}

copy($file, $dataPrinter);
?>
<script>
window.close();
</script>