<?php

use Google\Auth\ApplicationDefaultCredentials;
use Google\Cloud\Storage\StorageClient;

use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;

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

function start($videoFile, $path, $rateHertz = false)
{
    /**
     * Config
     */

    $transcribe_filename = 'kocakademi';
    $audio_ext = '.flac';

    /** !config **/

    $file = $videoFile;
    $result_array = [];

    /**
     * Transform Video
     */
    $list = video_list();
    foreach ($list as $item) {

        $category_id = $item[0];
        $set_id = $item[1];
        $video_id = $item[2];
        $cdn_url = $item[3];

        $dir = "$path/$set_id";
        $outAudioFile = "$video_id";
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $file_path = "$path/$set_id/$video_id" . $audio_ext;

        printf("$outAudioFile");
        printf(" \n");
        printf(date('H:i:s'));
        printf(" \n");

        /**
         * ffmpeg convertion
         */
        shell_exec("ffmpeg -i $cdn_url -loglevel error -ac 1  -f flac $file_path");
//        shell_exec("ffmpeg -i $cdn_url -loglevel error -vn -ac 1 -ar $rateHertz $file_path");

        /*
        $ffmpeg = FFMpeg\FFMpeg::create();
        $video = $ffmpeg->open($cdn_url);
        $format = new FFMpeg\Format\Audio\Flac();
//        $format->on('progress', function ($video, $format, $percentage) {
//            echo "$percentage % transcoded";
//        });
        $format
            ->setAudioChannels(1);
        $video->save($format, 'a.flac');
        */

        printf(date('H:i:s'));
        printf(" \n");

        $bucketName = "vt-86835.appspot.com";
        $objectName = "$set_id/$video_id" . $audio_ext;
        $source = "$file_path";

        /**
         * Upload audio file to gcp bucket
         */
        $result_text = upload_object($bucketName, $objectName, $source);

//        $result_array[] = [$set_id, $video_id, $result_text];
        $result_array = [$category_id, $set_id, $video_id, $result_text];

        /**
         * Write results to CSV file
         */
        write_to_csv($result_array, $transcribe_filename);
    }

    /**
     * Write results to CSV file
     */

//    write_to_csv($result_array, 'kisisel_gelisim');
//    return [$path, array_diff(scandir($path), array('.', '..'))];
}

/**
 * Upload a file.
 *
 * @param string $bucketName the name of your Google Cloud bucket.
 * @param string $objectName the name of the object.
 * @param string $source the path to the file to upload.
 *
 * @return string
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

    /**
     * Transcribe uploaded audio file (audio-to-text)
     */
    $uri = "gs://$bucketName/$objectName";

    $result_text = transcribe_async_gcs($uri);
    return "$result_text";
}


function transcribe_async_gcs($uri)
{

    /** Uncomment and populate these variables in your code */
// $uri = 'The Cloud Storage object to transcribe (gs://your-bucket-name/your-object-name)';

// change these variables if necessary
    $encoding = AudioEncoding::FLAC;
    //$sampleRateHertz = 48000;
    $languageCode = 'tr-TR';
    printf('Tag1 OK! ');

// set string as audio content
    $audio = (new RecognitionAudio())
        ->setUri($uri);
    printf('Tag2 OK! ');

// set config
    $config = (new RecognitionConfig())
        ->setEncoding($encoding)
        //->setSampleRateHertz($sampleRateHertz)
        ->setLanguageCode($languageCode);
    printf('Tag3 OK! ');

    putenv('GOOGLE_APPLICATION_CREDENTIALS=/Users/myuser/Desktop/Projects/speech-to-text/vt-86835-bd7270574cbc.json');
// create the speech client
    $client = new SpeechClient();

// create the asyncronous recognize operation
    $operation = $client->longRunningRecognize($config, $audio);
    printf('Tag4 OK! ');
    $operation->pollUntilComplete();
    printf('Tag5 OK! ');
    printf(" \n");

    $result_text = '';
    if ($operation->operationSucceeded()) {
        $response = $operation->getResult();

        // each result is for a consecutive portion of the audio. iterate
        // through them to get the transcripts for the entire audio file.
        printf('Transcript: ' . PHP_EOL);
        foreach ($response->getResults() as $result) {
            $alternatives = $result->getAlternatives();
            $mostLikely = $alternatives[0];
            $transcript = $mostLikely->getTranscript();
            $confidence = $mostLikely->getConfidence();

            $result_text .= $transcript;
            printf('%s' . PHP_EOL, $transcript);
            printf('Confidence: %s' . PHP_EOL, $confidence);
        }

    } else {
        $result_text = '';
        print_r($operation->getError());

    }

    $client->close();

    return "$result_text";
}

function write_to_csv($list, $file)
{

    //open file pointer to standard output *** a - append , w - (over)write
    $fp = fopen('transcribes/' . $file . '.csv', 'a');

    //add BOM to fix UTF-8 in Excel
    fputs($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    //OR
    //UTF-8 BOM (echo this on top of file if not generating csv file
    //only downloading it directly in the browser. )
    //echo "\xEF\xBB\xBF";

    if ($fp) {
//        foreach ($list as $item) {
//            fputcsv($fp, $item, ";");
//        }
        fputcsv($fp, $list, ";");
    }

    fclose($fp);

}

/**
 * https://t.yctin.com/en/excel/to-php-array/
 *
 * @return array
 */
function video_list()
{
    return array(
        0 => array('24', '304', '7135', 'https://website.com/304/3045996390113480HD.mp4'),
        1 => array('24', '304', '7134', 'https://website.com/304/3049896720115480HD.mp4'),
        2 => array('24', '304', '7133', 'https://website.com/304/3048654930114480HD.mp4'),
        3 => array('24', '304', '7132', 'https://website.com/304/3044991690112480HD.mp4'),
        4 => array('24', '304', '7131', 'https://website.com/304/3046930400111480HD.mp4'),
    );
}