<?php
require_once('vendor/autoload.php');
use RingCentral\SDK\SDK;
use RingCentral\SDK\Http\HttpException;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$env_file = "./environment/";
if (getenv('ENVIRONMENT') == "sandbox"){
   $env_file .= ".env-sandbox";
}else{
   $env_file .= ".env-production";
}
$_SESSION['env'] = $env_file;
$dotenv = new Dotenv\Dotenv(__DIR__, $env_file);
$dotenv->load();

$rcsdk = new SDK(getenv('RC_CLIENT_ID'), getenv('RC_CLIENT_SECRET'), getenv('RC_SERVER_URL'));
$platform = $rcsdk->platform();

$platform->login(getenv('RC_USERNAME'), getenv('RC_EXTENSION'), getenv('RC_PASSWORD'));
read_message_store_message_content();

function read_message_store_message_content(){
  global $platform;
  $params = array(
      'dateFrom' => '2019-01-01T23:59:59.999Z',
      'dateTo' => '2019-03-31T23:59:59.999Z'
      );
  $resp = $platform->get('/account/~/extension/~/message-store', $params);
  $contentPath = "content/";
  if (!file_exists($contentPath)) {
    mkdir($contentPath, 0777, true);
  }
  // Limit API call to ~40 calls per minute to avoid exceeding API rate limit.
  $timePerApiCall = 1.2;
  foreach ($resp->json()->records as $record){
    if (isset($record->attachments)){
        foreach($record->attachments as $attachment){
            $fileName = "";
            $fileExt = getFileExtensionFromMimeType($attachment->contentType);
            if ($record->type == "VoiceMail"){
                if ($attachment->type == "AudioRecording"){
                    $fileName = "voicemail_recording_".$record->attachments[0]->id.$fileExt;
                }else if ($attachment->type == "AudioTranscription" &&
                          $record->vmTranscriptionStatus == "Completed"){
                    $fileName = "voicemail_transcript_".$record->attachments[0]->id.".txt";
                }
            }else if ($record->type == "Fax"){
                $fileName = "fax_attachment_".$attachment->id.$fileExt;
            }else if ($record->type == "SMS"){
                $fileName = $record->attachments[0]->id;
                if ($attachment->type == "MmsAttachment"){
                    $fileName = "mms_attachment_".$record->attachments[0]->id.$fileExt;
                }else{
                    $fileName = "sms_text_".$record->attachments[0]->id.".txt";
                }
            }
            try {
              $res = $platform->get($attachment->uri);
              $start = microtime(true) * 1000;
              file_put_contents($contentPath.$fileName, $res->raw());
              $end = microtime(true) * 1000;
              $consumed = ($end - $start);
              if($consumed < $timePerApiCall) {
                sleep($timePerApiCall-$consumed);
              }
            }catch (ApiException $e) {
              $message = $e->getMessage();
              print 'Expected HTTP Error: ' . $message . PHP_EOL;
            }
        }
    }
  }
}

function getFileExtensionFromMimeType($mimeType){
  switch ($mimeType){
    case "text/html": return "html";
    case "text/css": return ".css";
    case "text/xml": return ".xml";
    case "text/plain": return ".txt";
    case "text/x-vcard": return ".vcf";
    case "application/msword": return ".doc";
    case "application/pdf": return ".pdf";
    case "application/rtf": return ".rtf";
    case "application/vnd.ms-excel": return ".xls";
    case "application/vnd.ms-powerpoint": return ".ppt";
    case "application/zip": return ".zip";
    case "image/tiff": return ".tiff";
    case "image/gif": return ".gif";
    case "image/jpeg": return ".jpg";
    case "image/png": return ".png";
    case "image/x-ms-bmp": return ".bmp";
    case "image/svg+xml": return ".svg";
    case "audio/wav":
    case "audio/x-wav":
      return ".wav";
    case "audio/mpeg": return ".mp3";
    case "audio/ogg": return ".ogg";
    case "video/3gpp": return ".3gp";
    case "video/mpeg": return ".mpeg";
    case "video/quicktime": return ".mov";
    case "video/x-flv": return ".flv";
    case "video/x-ms-wmv": return ".wmv";
    case "video/mp4": return ".mp4";
    default: return ".unknown";
  }
}
