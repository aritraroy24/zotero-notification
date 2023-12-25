<?php
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

function smtp_mailer($group_name, $id, $type, $title, $message)
{

    $SENDGRID_API_KEY = $_ENV['SENDGRID_API_KEY'];
    $FROM_EMAIL = $_ENV['FROM_EMAIL'];
    $TO_EMAIL = $_ENV['TO_EMAIL'];
    $sub = "New item has been added to your Zotero Group";

    try {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/');
        $twig = new \Twig\Environment($loader);

        $template = $twig->load('email_template.twig');

    } catch (Exception $e) {
        echo 'Caught exception: ' . $e->getMessage() . "\n";
    }

    $emailBody = $template->render([
        'group_name' => $group_name,
        'id' => $id,
        'type' => $type,
        'title' => $title,
        'message' => $message
    ]);


    $email = new \SendGrid\Mail\Mail();
    $email->setFrom($FROM_EMAIL, "Zotero Notification");
    $email->setSubject($sub);
    $email->addTo($TO_EMAIL, "Aritra Roy");
    $email->addContent(
        "text/html", $emailBody
    );

    $sendgrid = new \SendGrid($SENDGRID_API_KEY);

    try {
        $sendgrid->send($email);
    } catch (Exception $e) {
        echo 'Caught exception: ' . $e->getMessage() . "\n";
    }
}

function writeFileAndCheck($folderName, $itemId, $itemData, $itemType, $groupName, $titleData, $userID, $message = null)
{
    $myZoteroUserId = $_ENV["ZOTERO_USERID"];
    if ($myZoteroUserId != $userID) {
        $folderPath = 'zotero-groups/' . $groupName . "/" . $folderName;
        try {
            if (!is_dir($folderPath)) {
                mkdir($folderPath, 0755, true);
            }
        } catch (\Throwable $th) {
            echo $th;
        }
        $filePath = $folderPath . '/' . $itemId . '.txt';
        if (!file_exists($filePath)) {
            file_put_contents($filePath, $itemData);
            echo "I'll send an email for item " . $itemId . ' - ' . $itemType . ' - ' . $titleData . "\n";
            if ($message === null) {
                smtp_mailer($groupName, $itemId, $itemType, $titleData, "Not Applicable");
            } else {
                smtp_mailer($groupName, $itemId, $itemType, $titleData, $message);
            }
        }
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
                $userId = $dataItem["meta"]["createdByUser"]["id"];
                if ($dataItemType === "journalArticle") {
                    $journalArticles = "Item Key: " . $dataItemKey . "\n" . "Type: Journal Article" . "\n" . "Title: " . $dataItem["data"]["title"];
                    writeFileAndCheck("journal_article/", $dataItemKey, $journalArticles, "Journal Article", $groupName, $dataItem["data"]["title"], $userId);
                } elseif ($dataItemType === "note") {
                    $standAloneNote = "Item Key: " . $dataItemKey . "\n" . "Type: Stand Alone Note";
                    writeFileAndCheck("stand_alone_note/", $dataItemKey, $standAloneNote, "Stand Alone Note", $groupName, "Not Applicable [Stand Alone Note]", $userId);
                } else {
                    $parentItemId = $dataItem['data']['parentItem'];
                    $parentItemUrl = 'https://api.zotero.org/groups/' . $item . '/items/' . $parentItemId . '?key=' . $zoteroApi;
                    $parentItemTitle = json_decode(file_get_contents($parentItemUrl), true)['data']['title'];
                    if ($dataItemType === "note") {
                        $note = "Item Key: " . $dataItemKey . "\n" . "Type: Note" . "\n" . "Title: " . $parentItemTitle;
                        writeFileAndCheck("notes/", $dataItemKey, $note, "Note", $groupName, $parentItemTitle . " [Parent Element]", $userId);
                    } elseif ($dataItemType === "attachment") {
                        $attachment = "Item Key: " . $dataItemKey . "\n" . "Type: Attachment" . "\n" . "Title: " . $parentItemTitle;
                        writeFileAndCheck("attachments/", $dataItemKey, $attachment, "Attachment", $groupName, $parentItemTitle . ' [Parent Element]', $userId);
                    } elseif ($dataItemType === "book") {
                        $book = "Item Key: " . $dataItemKey . "\n" . "Type: Book" . "\n" . "Title: " . $parentItemTitle;
                        writeFileAndCheck("books/" . $dataItemType . "/", $dataItemKey, $book, "Book", $groupName, $parentItemTitle . ' [Parent Element]', $userId);
                    } elseif ($dataItem['data']['annotationComment'] !== "") {
                        $annotationComments = "Item Key: " . $dataItemKey . "\n" . "Type: Annotation Comment" . "\n" . "Title: " . $parentItemTitle . "\n" . "Comment Details: " . $dataItem['data']['annotationComment'];
                        writeFileAndCheck("comments/", $dataItemKey, $annotationComments, "Annotation Comment", $groupName, $parentItemTitle . " [Parent Element]", $userId, $dataItem['data']['annotationComment']);
                    } elseif ($dataItemType === "annotation") {
                        $annotation = "Item Key: " . $dataItemKey . "\n" . "Type: Annotation" . "\n" . "Annotation Text: " . $dataItem['data']['annotationText'];
                        writeFileAndCheck("annotations/", $dataItemKey, $annotation, "Annotation Text", $groupName, $parentItemTitle . ' [Parent Element]', $userId, $dataItem['data']['annotationText']);
                    } else {
                        $othersData = "Item Key: " . $dataItemKey . "\n" . "Type:" . $dataItemType;
                        writeFileAndCheck("others/" . $dataItemType . "/", $dataItemKey, $othersData, $dataItemType, $groupName, "Not Applicable", $userId);
                    }
                }
            }
        }
    }
} else {
    echo 'Error decoding JSON';
}

?>