<?php

# [START upload_object]
use Google\Cloud\Storage\StorageClient;

/**
 * Transforms an video file using FFMPEG API
 * Example:
 * ```
 * transform_video('videofile.mp4', '/path/to/file');
 * ```.
 *
 * @param string $videoFile path to an video file.
 * @param string $path path to store the transformed audio files.
 * @param string $rateHertz sample rate (in Hertz) to transformed audio files.
 *
 * @return string the array of [ path to audio files, string (audio files names)] transformed
 */

function transform_video($videoFile, $path, $rateHertz)
{

    $file = $videoFile;
    $list = video_list();
    foreach ($list as $item) {

        $dir = "$path/$item[0]";
        $outAudioFile = basename(substr($item[1], 0, strrpos($item[1], ".")));
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        printf("$outAudioFile") ;
        printf(" \n") ;
        printf(date('H:i:s'));
        printf(" \n") ;

//	shell_exec("ffmpeg -i $videoFile -loglevel error -f segment -segment_time 180 -ac 1 -ar $rateHertz -ss 00:00:00 -to 03:04:59 $path/$outAudioFile".".flac");
        shell_exec("ffmpeg -y -i $item[1] -loglevel error -ac 1 -ar $rateHertz $dir/$outAudioFile" . ".flac");

        printf(date('H:i:s'));
        printf(" \n") ;

        $bucketName = "vt-86835.appspot.com";
        $objectName = "$item[0]/$outAudioFile" . ".flac";
        $source = "$dir/$outAudioFile" . ".flac";

        upload_object($bucketName, $objectName, $source);
    }

//    return [$path, array_diff(scandir($path), array('.', '..'))];
}

/**
 * Upload a file.
 *
 * @param string $bucketName the name of your Google Cloud bucket.
 * @param string $objectName the name of the object.
 * @param string $source the path to the file to upload.
 *
 * @return Psr\Http\Message\StreamInterface
 */
function upload_object($bucketName, $objectName, $source)
{
    $storage = new StorageClient([
        'keyFilePath' => 'vt-86835-bd7270574cbc.json',
        'projectId' => 'vt-86835'
    ]);
//    $file = fopen($source, 'r');
    $file = file_get_contents($source);
    $bucket = $storage->bucket($bucketName);
    $object = $bucket->upload($file, [
        'name' => $objectName
    ]);
    printf('Uploaded %s to gs://%s/%s' . PHP_EOL, basename($source), $bucketName, $objectName);
}

function transform_video_default($videoFile, $path, $rateHertz)
{
    printf(date('H:i:s'));
    $outAudioFile = basename(substr($videoFile, 0, strrpos($videoFile, ".")));
    $path = "$path";
//  $path = "$path/$outAudioFile";
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
//	shell_exec("ffmpeg -i $videoFile -loglevel error -f segment -segment_time 180 -ac 1 -ar $rateHertz -ss 00:00:00 -to 03:04:59 $path/$outAudioFile".".flac");
    shell_exec("ffmpeg -i $videoFile -loglevel error -f segment -segment_time 1800 -ac 1 -ar $rateHertz $path/$outAudioFile" . "_%02d.flac");
    return [$path, array_diff(scandir($path), array('.', '..'))];
}

/**
 * https://t.yctin.com/en/excel/to-php-array/
 *
 * @return array
 */
function video_list()
{
    return array(
        0 => array('2108', 'https://website.com/sample.mp4'),
        1 => array('2108', 'https://website.com/sample.mp4')
    );

}