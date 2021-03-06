<?php
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/Token.php';

/**
 * Class that operates with google drive api
 * At first create and download your creadentials here: https://console.developers.google.com/apis/credentials
 * Set "Authorized redirect URIs" as the redirect URI
 *
 * @author ondrej-maxa
 * @version 1.0.0
 */
class GoogleDriveApi
{
    /**
     * Email of logged user.
     *
     * @var string
     */
    private $email;

    /**
     * Your google drive client.
     *
     * @var Google_Client
     */
    private $client;

    /**
     * Client's token.
     *
     * @var Token
     */
    private $token;

    /**
     * Service that operates with google drive.
     *
     * @var Google_Service_Drive
     */
    private $service;

    /**
     * Owner permission.
     *
     * @var Google_Service_Drive_Permission
     */
    private $ownerPermission;

    /**
     * Path to your credentials.
     *
     * @var string
     */
    private $pathToCredentials;

    /**
     * Email that will be set as an owner of all files
     *
     * @final string
     */
    const OWNER_EMAIL = "";

    /**
     * Emails, that are allowed as owner
     *
     * @final array
     */
    const VERIFIED_EMAILS = array();

    /**
     * Id of root directory.
     */
    const ROOT_DIR = "root";

    /**
     * Constructor of this class
     *
     * @param string $pathToCredentials This is the path to downloaded credentials
     */
    public function __construct($pathToCredentials)
    {
        $this->pathToCredentials = $pathToCredentials;
        $this->initClient();
        $this->token             = new Token($this->client);
        $this->service           = new Google_Service_Drive($this->client);
        $this->email             = $this->getUsersEmail();
        $this->ownerPermission   = new Google_Service_Drive_Permission(array(
            'type' => 'user',
            'role' => 'owner',
            'emailAddress' => self::OWNER_EMAIL
        ));
    }

    /**
     * Getter of $email
     *
     * @return string Email of logged user.
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Method that logs you out from google drive
     */
    public function logOut()
    {
        $this->token->reset();
    }

    /**
     * List all files that are associated with your account.
     *
     * @param string $fields Fields of files.
     * @return array Array of all files.
     */
    public function listFiles($fields = "files(id, name, owners)")
    {
        $result    = array();
        $pageToken = null;

        do {
            $parameters = array(
                "fields" => "nextPageToken, $fields"
            );
            if ($pageToken) {
                $parameters['pageToken'] = $pageToken;
            }
            $files = $this->service->files->listFiles($parameters);

            $result = array_merge($result, $files->getFiles());
            try {
                $pageToken = $files->getNextPageToken();
            } catch (Exception $e) {
                print "An error occurred: ".$e->getMessage();
                $pageToken = null;
            }
        } while ($pageToken);
        return $result;
    }

    /**
     * Creates new folder
     *
     * @param string $name
     * @param string $parentId
     * @return string Id of created folder
     */
    public function createFolder($name, $parentId = ROOT_DIR)
    {
        $fileMetadata = new Google_Service_Drive_DriveFile(array(
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => array($parentId)
        ));
        $file         = $this->service->files->create($fileMetadata,
            array(
            'fields' => 'id'));
        return $file['id'];
    }

    /**
     * Creates new file
     *
     * @param string $fullName Full name of file ex. document1.docx
     * @param string $mimeType Mime type ex. image/png
     * @param string $fullPath Path to file
     * @param string $parentId Id of parent to be uploaded
     * @return string
     */
    public function uploadFile($fullName, $mimeType, $fullPath,
                               $parentId = ROOT_DIR)
    {
        $content      = file_get_contents($fullPath);
        $fileMetadata = new Google_Service_Drive_DriveFile(array(
            'name' => $fullName,
            'parents' => array($parentId)
        ));
        $file         = $this->service->files->create($fileMetadata,
            array(
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id'
        ));
        return $file['id'];
    }

    /**
     * Creates new file
     *
     * @param string $name Name of file ex. document1
     * @param string $type Type of file ex. image
     * @param string $extension Extension of file ex. docx
     * @param string $pathToFileDir Path to directory where the file is located ex. /path/to/directory
     * @param string $parentId Id of parent to be uploaded
     * @return string Id of created file
     */
    public function uploadFileBasic($name, $type, $extension, $pathToFileDir,
                                    $parentId = ROOT_DIR)
    {
        return $this->uploadFile("$name.$extension", "$type/$extension",
                $pathToFileDir."/$name.$extension", $parentId);
    }

    /**
     * Creates new empty file
     *
     * @param string $name Name of file ex. document1
     * @param string $type Type of file ex. image
     * @param string $extension Extension of file ex. docx
     * @param string $parentId Id of parent to be uploaded
     * @return string Id of created file
     */
    public function createFile($fullName, $mimeType, $parentId = ROOT_DIR)
    {
        $fileMetadata = new Google_Service_Drive_DriveFile(array(
            'name' => $fullName,
            'parents' => array($parentId)
        ));
        $file         = $this->service->files->create($fileMetadata,
            array(
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id'
        ));
        return $file['id'];
    }

    /**
     * Creates new file
     *
     * @param string $name Name of file ex. document1
     * @param string $type Type of file ex. image
     * @param string $extension Extension of file ex. docx
     * @param string $parentId Id of parent to be uploaded
     * @return string Id of created file
     */
    public function createFileBasic($name, $type, $extension,
                                    $parentId = ROOT_DIR)
    {
        return $this->createFile("$name.$extension", "$type/$extension",
                $parentId);
    }

    /**
     * Moves file to another folder
     *
     * @param string $fileId File to be move
     * @param string $folderId Final folder
     */
    public function moveFile($fileId, $folderId)
    {
        $emptyFileMetadata = new Google_Service_Drive_DriveFile();
        $file              = $this->service->files->get($fileId,
            array('fields' => 'parents'));
        $previousParents   = join(',', $file->parents);
        $this->service->files->update($fileId, $emptyFileMetadata,
            array(
            'addParents' => $folderId,
            'removeParents' => $previousParents,
            'fields' => 'id, parents'));
    }

    /**
     * Downloads file content
     *
     * @param string $fileId
     * @return file_content
     */
    public function downloadFile($fileId)
    {
        $response = $this->service->files->get($fileId,
            array(
            'alt' => 'media'));
        $content  = $response->getBody()->getContents();
        return $content;
    }

    /**
     * List all files that aren't owned by allowed users.
     *
     * @return array $filesInDanger Array of files in danger.
     */
    public function testFilesOwners()
    {
        $files         = $this->listFiles();
        $filesInDanger = array();
        foreach ($files as $file) {
            foreach ($file['owners'] as $owner) {
                if (in_array($owner['emailAddress'], self::VERIFIED_EMAILS)) {
                    continue;
                }
                array_push($filesInDanger, $file);
            }
        }
        return $filesInDanger;
    }

    /**
     * Set owner of files with $ids to allowed owner.
     *
     * @param array $ids Ids of files to be changed.
     */
    public function setVerifiedOwner($ids)
    {
        $files = $this->getFilesByIds($ids);
        foreach ($files as $file) {
            $this->service->permissions->create(
                $file['id'], $this->ownerPermission,
                array('transferOwnership' => 'true')
            );
        }
    }

    /**
     * Lists all files with $ids.
     *
     * @param array $ids Ids of files to be listed.
     * @return array $filesToBeReturned Files with id same as in $ids.
     */
    private function getFilesByIds($ids)
    {
        $files             = $this->listFiles("files(id, name, permissions)");
        $filesToBeReturned = array();
        foreach ($files as $file) {
            if (in_array($file['id'], $ids)) {
                array_push($filesToBeReturned, $file);
            }
        }
        return $filesToBeReturned;
    }

    /**
     * Gets email of logged user.
     *
     * @return string $email Email of logged user.
     */
    private function getUsersEmail()
    {
        $about = $this->service->about->get(array("fields" => "user"));
        return $about->getUser()['emailAddress'];
    }

    /**
     * Inits Google Client
     */
    private function initClient()
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Google Drive API');
        $this->client->setScopes(Google_Service_Drive::DRIVE);
        $this->client->setAuthConfig($this->pathToCredentials);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
    }
}