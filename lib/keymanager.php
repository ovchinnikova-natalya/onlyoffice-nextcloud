<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\Onlyoffice;

use OCP\Files\File;

/**
 * Key manager
 *
 * @package OCA\Onlyoffice
 */
class KeyManager {

    /**
     * Table name
     */
    private const TableName_Key = "onlyoffice_filekey";

    /**
     * Get document identifier
     *
     * @param integer $fileId - file identifier
     *
     * @return string
     */
    public static function get($fileId) {
        $connection = \OC::$server->getDatabaseConnection();
        $select = $connection->prepare("
            SELECT `key`
            FROM  `*PREFIX*" . self::TableName_Key . "`
            WHERE `file_id` = ?
        ");
        $result = $select->execute([$fileId]);

        $keys = $result ? $select->fetch() : [];
        $key = is_array($keys) && isset($keys["key"]) ? $keys["key"] : "";

        return $key;
    }

    /**
     * Store document identifier
     *
     * @param integer $fileId - file identifier
     * @param integer $key - file key
     *
     * @return bool
     */
    public static function set($fileId, $key) {
        $connection = \OC::$server->getDatabaseConnection();
        $insert = $connection->prepare("
            INSERT INTO `*PREFIX*" . self::TableName_Key . "`
                (`file_id`, `key`)
            VALUES (?, ?)
        ");
        return (bool)$insert->execute([$fileId, $key]);
    }

    /**
     * Delete document identifier
     *
     * @param integer $fileId - file identifier
     * @param bool $unlock - delete even with lock label
     *
     * @return bool
     */
    public static function delete($fileId, $unlock = false) {
        $connection = \OC::$server->getDatabaseConnection();
        $delete = $connection->prepare("
            DELETE FROM `*PREFIX*" . self::TableName_Key . "`
            WHERE `file_id` = ?
            " . ($unlock === false ? "AND `lock` != 1" : "")
        );
        return (bool)$delete->execute([$fileId]);
    }

    /**
     * Change lock status
     *
     * @param integer $fileId - file identifier
     * @param integer $lock - status
     *
     * @return bool
     */
    public static function lock($fileId, $lock = true) {
        $connection = \OC::$server->getDatabaseConnection();
        $update = $connection->prepare("
            UPDATE `*PREFIX*" . self::TableName_Key . "`
            SET `lock` = ?
            WHERE `file_id` = ?
        ");
        return (bool)$update->execute([$lock === true ? 1 : 0, $fileId]);
    }

    /**
     * Change forcesave status
     *
     * @param integer $fileId - file identifier
     * @param integer $fs - status
     *
     * @return bool
     */
    public static function setForcesave($fileId, $fs = true) {
        $connection = \OC::$server->getDatabaseConnection();
        $update = $connection->prepare("
            UPDATE `*PREFIX*" . self::TableName_Key . "`
            SET `fs` = ?
            WHERE `file_id` = ?
        ");
        return (bool)$update->execute([$fs === true ? 1 : 0, $fileId]);
    }

    /**
     * Get forcesave status
     *
     * @param integer $fileId - file identifier
     *
     * @return bool
     */
    public static function wasForcesave($fileId) {
        $connection = \OC::$server->getDatabaseConnection();
        $select = $connection->prepare("
            SELECT `fs`
            FROM  `*PREFIX*" . self::TableName_Key . "`
            WHERE `file_id` = ?
        ");
        $result = $select->execute([$fileId]);

        $rows = $result ? $select->fetch() : [];
        $fs = is_array($rows) && isset($rows["fs"]) ? $rows["fs"] : "";

        return $fs === "1";
    }

    /**
     * Change lock status in the federated share
     *
     * @param File $file - file
     * @param bool $lock - status
     * 
     */
    public static function lockFederatedKey($file, $lock = true) {
        $remote = $file->getStorage()->getRemote();
        $shareToken = $file->getStorage()->getToken();
        $internalPath = $file->getInternalPath();

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();
        $response = $client->post($remote . "ocs/v2.php/apps/" . $this->appName . "/api/v1/keylock?format=json", [
            "timeout" => 5,
            "body" => [
                "shareToken" => $shareToken,
                "path" => $internalPath,
                "lock" => $lock
            ]
        ]);
        $body = \json_decode($response->getBody(), true);

        $data = $body["ocs"]["data"];

        if(empty($data)) {
            $this->logger->debug("Federated request lock for " . $fileId . " is successful", ["app" => $this->appName]);
        }

        if (!empty($data["error"])) {
            $this->logger->error("Error lock federated key " . $data["error"], ["app" => $this->appName]);
        }
    }

    /**
     * Change lock status in the federated share
     *
     * @param File $file - file
     * @param bool $lock - status
     * @param bool $fs - status
     * 
     */
    public static function unlockFederatedKey($file, $lock = false, $fs = true) {
        $remote = $file->getStorage()->getRemote();
        $shareToken = $file->getStorage()->getToken();
        $internalPath = $file->getInternalPath();

        $httpClientService = \OC::$server->getHTTPClientService();
        $client = $httpClientService->newClient();
        $response = $client->post($remote . "ocs/v2.php/apps/" . $this->appName . "/api/v1/keyunlock?format=json", [
            "timeout" => 5,
            "body" => [
                "shareToken" => $shareToken,
                "path" => $internalPath,
                "lock" => $lock,
                "fs" => $fs
            ]
        ]);
        $body = \json_decode($response->getBody(), true);

        $data = $body["ocs"]["data"];

        if(empty($data)) {
            $this->logger->debug("Federated request unlock for " . $fileId . " is successful", ["app" => $this->appName]);
        }

        if (!empty($data["error"])) {
            $this->logger->error("Error unlock federated key " . $data["error"], ["app" => $this->appName]);
        }
    }
}
