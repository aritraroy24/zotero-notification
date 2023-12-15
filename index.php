<?php
include('smtp/PHPMailerAutoload.php');
require 'vendor/autoload.php';
header('Content-type: text/plain');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$noteFilePath = 'notes.txt';
$annotationFilePath = 'annotation.txt';
$commentFilePath = 'comments.txt';
$othersFilePath = 'others.txt';

$zoteroApi = $_ENV["ZOTERO_API"];
$zoteroUserId = $_ENV["ZOTERO_USERID"];

$groupApiUrl = 'https://api.zotero.org/users/' . $zoteroUserId . '/groups?key=' . $zoteroApi;

$groupsDataResponse = file_get_contents($groupApiUrl);
$groupsDataArray = json_decode($groupsDataResponse, true);

// function smtp_mailer($to, $subject, $msg)
// {
//     $GMAIL_APP_PASSWORD = $_ENV['GMAIL_APP_PASSWORD'];
//     $FROM_EMAIL = $_ENV['FROM_EMAIL'];
//     $mail = new PHPMailer();
//     $mail->IsSMTP();
//     $mail->SMTPAuth = true;
//     $mail->SMTPSecure = 'tls';
//     $mail->Host = "smtp.gmail.com";
//     $mail->Port = 587;
//     $mail->IsHTML(true);
//     $mail->CharSet = 'UTF-8';
//     //$mail->SMTPDebug = 2; 
//     $mail->Username = $FROM_EMAIL;
//     $mail->GMAIL_APP_PASSWORD = $GMAIL_APP_PASSWORD;
//     $mail->SetFrom($FROM_EMAIL);
//     $mail->Subject = $subject;
//     $mail->Body = $msg;
//     $mail->AddAddress($to);
//     $mail->SMTPOptions = array('ssl' => array(
//         'verify_peer' => false,
//         'verify_peer_name' => false,
//         'allow_self_signed' => false
//     ));
//     if (!$mail->Send()) {
//         echo $mail->ErrorInfo;
//     } else {
//         return 'Sent';
//     }
// }

function writeFileAndCheck($folderName, $itemId, $itemData, $itemType, $groupName)
{
    $folderPath = $groupName . "/" . $folderName;
    try {
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }
    } catch (\Throwable $th) {
        echo $th;
    }
    $filePath = $itemId . '.txt';
    if (!file_exists($filePath)) {
        file_put_contents($folderPath . $filePath, $itemData);
        // SENDING EMAIL
        // $TO_EMAIL = $_ENV['TO_EMAIL'];
        // $sub = "New item(s) has(have) been added to your Zotero Group";
        // smtp_mailer($TO_EMAIL, $sub, 'Hello World');
    }
}

if ($groupsDataArray !== null) {
    $groupIdsArray = [];
    foreach ($groupsDataArray as $item) {
        if (isset($item['data']['id'])) {
            $groupIdsArray[] = $item['data']['id'];
        }
    }
    foreach ($groupIdsArray as $item) {
        $contentApiUrl = 'https://api.zotero.org/groups/' . $item . '/items?key=' . $zoteroApi;
        $itemsDataArray = json_decode(file_get_contents($contentApiUrl), true);
        $groupName = $itemsDataArray[0]["library"]["name"];
        if ($itemsDataArray !== null) {
            foreach ($itemsDataArray as $dataItem) {
                $dataItemKey = $dataItem["key"];
                $dataItemType = $dataItem['data']['itemType'];
                if ($dataItemType === "journalArticle") {
                    $journalArticles = "Item Key: " . $dataItemKey . "\n" . "Type: Journal Article" . "\n" . "Title: " . $dataItem["data"]["title"];
                    writeFileAndCheck("journal_article/", $dataItemKey, $journalArticles, "journalArticle", $groupName);
                } elseif ($dataItemType === "note") {
                    $standAloneNote = "Item Key: " . $dataItemKey . "\n" . "Type: Stand Alone Note";
                    writeFileAndCheck("stand_alone_note/", $dataItemKey, $standAloneNote, "note", $groupName);
                } else {
                    $parentItemId = $dataItem['data']['parentItem'];
                    $parentItemUrl = 'https://api.zotero.org/groups/' . $item . '/items/' . $parentItemId . '?key=' . $zoteroApi;
                    $parentItemTitle = json_decode(file_get_contents($parentItemUrl), true)['data']['title'];
                    if ($dataItemType === "note") {
                        $note = "Item Key: " . $dataItemKey . "\n" . "Type: Note" . "\n" . "Title: " . $parentItemTitle;
                        writeFileAndCheck("notes/", $dataItemKey, $note, "note", $groupName);
                    } elseif ($dataItemType === "attachment") {
                        $attachment = "Item Key: " . $dataItemKey . "\n" . "Type: Attachment" . "\n" . "Title: " . $parentItemTitle;
                        writeFileAndCheck("attachments/", $dataItemKey, $attachment, "attachment", $groupName);
                    } elseif ($dataItemType === "book") {
                        $book = "Item Key: " . $dataItemKey . "\n" . "Type: Book" . "\n" . "Title: " . $parentItemTitle;
                        writeFileAndCheck("books/" . $dataItemType . "/", $dataItemKey, $book, "book", $groupName);
                    } elseif ($dataItem['data']['annotationComment'] !== "") {
                        $annotationComments = "Item Key: " . $dataItemKey . "\n" . "Type: Annotation Comment" . "\n" . "Title: " . $parentItemTitle . "\n" . "Comment Details: " . $dataItem['data']['annotationComment'];
                        writeFileAndCheck("comments/", $dataItemKey, $annotationComments, "annotationComment", $groupName);
                    } else {
                        $othersData = "Item Key: " . $dataItemKey . "\n" . "Type:" . $dataItemType;
                        writeFileAndCheck("others/", $dataItemKey, $othersData, $dataItemType, $groupName);
                    }
                }
            }
        }
    }
} else {
    echo 'Error decoding JSON';
}

?>