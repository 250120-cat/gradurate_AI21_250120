<?php
$dir = __DIR__ . '/..';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$errors = [];
foreach ($it as $f){
    if ($f->isFile() && strtolower($f->getExtension()) === 'php'){
        $path = $f->getPathname();
        $out = null;
        $ret = null;
        exec('"C:\\xampp\\php\\php.exe" -l "' . $path . '" 2>&1', $out, $ret);
        if ($ret !== 0){
            $errors[$path] = $out;
        }
    }
}
if (count($errors) === 0){
    echo "No syntax errors detected\n";
    exit(0);
}
foreach ($errors as $p => $o){
    echo "ERROR in: $p\n";
    echo implode("\n", $o) . "\n\n";
}
exit(1);
