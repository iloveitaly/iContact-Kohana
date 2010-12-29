<?php
/**
 * @package     Standard
 * @subpackage  Libraries
 * @author		Michael Bianco <info@mabwebdesign.com>, <http://mabblog.com>
 * 
 */

// Requires Kohana Curl Module: http://dev.kohanaframework.org/projects/curl

class IContact_Core {
	const STATUS_CODE_SUCCESS = 200;
	public $queryOptions = array('limit' => 50);
	
	public function __construct() {
		$this->accountID = null;
		$this->clientFolderID = null;
		
		// if you are creating an iContact instance you are going to need authentication information
		$this->getAuthInfo();
	}
	
	// upload contact list functions
	
	public function uploadContactList($query, $listName, $labels = '') {
		$db = new Database();
		$list = $db->query($query)->as_array();
		$fileName = tempnam('/tmp', 'icontact');
		$handle = fopen($fileName, "w+");
		
		if($labels) {
			fwrite($handle, $labels);
		}
		
		foreach($list as $item) {
			fputcsv($handle, (array) $item);
		}
		
		$listID = $this->getListIDWithName($listName);
		$this->uploadContactFile($fileName, $listID);
		
		fclose($handle);
		unlink($fileName);
	}
	
	public function uploadContactFile($file, $listID) {
		$referenceID = $this->createUploadReference($listID);
		
		if(!$referenceID) return false;
		
		$this->uploadData($referenceID, $file);
		
		while(($status = $this->getUploadStatus($referenceID)) != 'complete') {
			sleep(1);
		}
		
		return true;
	}
	
	public function subscribeContactToList($contactInformation, $listName) {
		$listID = $this->getListIDWithName($listName);
		
		if($listID === false) {
			Kohana::log('error', 'Error finding iContact list with name '.$listName);
			return false;
		}
		
		// add contact
		$contactID = $this->addContact($contactInformation);
		
		if($contactID === false) {
			Kohana::log('error', 'Error adding contact to iContact');
			return false;
		}
		
		$result = $this->subscribeContactIDToListID($contactID, $listID);
		
		if($result === false) {
			Kohana::log('error', 'Error adding iContat contact to list');
		}
		
		return $result;
	}
	
	public function subscribeContactIDToListID($contactID, $listID) {
		$response = $this->callResource("/a/{$this->accountID}/c/{$this->clientFolderID}/subscriptions",
			'POST', array(
				array(
					'contactId' => $contactID,
					'listId'    => $listID,
					'status'    => 'normal',
				),
			));

		if($response['code'] == self::STATUS_CODE_SUCCESS) {
			if(!empty($response['data']['warnings'])) {
				Kohana::log('error', 'There was warnings when generating an upload reference: '.print_r($response, true));
			}
			
			return true;
		} else {
			Kohana::log('error', 'Error subscribing contact to list: '.$response['code']);
			Kohana::log('error', 'iContact response data: '.print_r($response['data'], true));
			
			return false;
		}
	}
	
	public function addContact($contactInformation) {
		/*
		Example Subscription:
		
		'firstName' => 'John',
		'lastName'  => 'Doe',
		'email'     => 'email'
		*/
		
		$response = $this->callResource("/a/{$this->accountID}/c/{$this->clientFolderID}/contacts", 'POST', array($contactInformation));

		if($response['code'] == self::STATUS_CODE_SUCCESS) {
			$contactID = $response['data']['contacts'][0]['contactId'];
			
			if (!empty($response['data']['warnings'])) {
				Kohana::log('error', 'There was warnings when creating a contact reference: '.print_r($response, true));
			}
			
			return $contactID;
		} else {
			Kohana::log('error', 'Error creating upload reference with code: '.$response['code']);
			Kohana::log('error', 'iContact response data: '.print_r($response['data'], true));
			
			return false;
		}
	}
	
	protected function getListIDWithName($listName) {
		$listData = $this->callResource("/a/{$this->accountID}/c/{$this->clientFolderID}/lists".$this->getQueryOptions(), 'GET');

		foreach($listData['data']['lists'] as $listItem) {
			if($listItem['name'] == $listName) {
				return $listItem['listId'];
			}
		}
		
		return false;
	}
	
	protected function createUploadReference($listID) {
		$uploadId = null;

		$response = $this->callResource("/a/{$this->accountID}/c/{$this->clientFolderID}/uploads",
			'POST', array(
				array(
					'action' => 'add',
					'listIds'  => $listID
				)
			)
		);

		if ($response['code'] == self::STATUS_CODE_SUCCESS) {
			if(!isset($response['data']['uploads']['0'])) {
				Kohana::log('Reported success, but encountered unexpected data structure: '.print_r($response, true));
				return;
			}
			
			$uploadId = $response['data']['uploads']['0']['uploadId'];

			$warningCount = 0;
			
			if (!empty($response['data']['warnings'])) {
				Kohana::log('error', 'There was warnings when generating an upload reference: '.print_r($response, true));
			}
		} else {
			Kohana::log('error', 'Error creating upload reference with code: '.$response['code']);
			Kohana::log('error', 'iContact response data: '.print_r($response['data'], true));
			
			return false;
		}

		return $uploadId;
	}
	
	protected function uploadData($uploadId, $file) {
		$response = $this->callResource("/a/{$this->accountID}/c/{$this->clientFolderID}/uploads/{$uploadId}/data", 'PUT', $file);

		if ($response['code'] == self::STATUS_CODE_SUCCESS) {
			$uploadId = $response['data']['uploadId'];

			if (!empty($response['data']['warnings'])) {
				Kohana::log('error', 'There was warnings when uploading icontact data. '.print_r($response, true));
			}
		} else {
			Kohana::log('error', 'There was an error while uploading iContact data. '.print_r($response, true));
			
			return false;
		}
		
		return true;
	}
	
	protected function getUploadStatus($uploadId) {
		$status = null;
		$response = $this->callResource("/a/{$this->accountID}/c/{$this->clientFolderID}/uploads/{$uploadId}", 'GET');

		if ($response['code'] == self::STATUS_CODE_SUCCESS) {
			$status = $response['data']['upload']['status'];

			if (!empty($response['data']['upload']['warnings'])) {
				Kohana::log('error', 'There was warnings when getting upload status: '.print_r($response, true));
			}
		} else {
			$status = 'complete';

			Kohana::log('error', 'There was an error while uploading icontact: '.print_r($response, true));
		}

		return $status;
	}
	
	// each request can have a bunch of query string options
	// http://developer.icontact.com/documentation/icontact-application-programming-interface-api#restLikeArchitecture
	protected function getQueryOptions() {
		if(empty($this->queryOptions)) return '';
		
		return '?'.http_build_query($this->queryOptions, '', '&');
	}
	
	// authentication
	
	public function getAuthInfo() {
		// grab the account ID
		$accountIDData = $this->callResource('/a/', 'GET');
		$this->accountID = $accountIDData['data']['accounts']['0']['accountId'];
		
		// grab the client folder
		$clientFolderData = $this->callResource('/a/'.$this->accountID.'/c/', 'GET');
		$this->clientFolderID = $clientFolderData['data']['clientfolders']['0']['clientFolderId'];
	}	
	
	protected function callResource($url, $method, $data = null) {
		$url    = Kohana::config('icontact.app_url').$url;
		$handle = new Curl();

		$handle->setopt_array(array(
			CURLOPT_URL => $url,
			CURLOPT_HTTPHEADER => array(
				'Accept: application/json',
				'Content-Type: application/json',
				'Api-Version: 2.0',
				'Api-AppId: ' . Kohana::config('icontact.app_id'),
				'Api-Username: ' . Kohana::config('icontact.username'),
				'Api-Password: ' . Kohana::config('icontact.password'),
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_POST => FALSE
		));

		switch($method) {
			case 'POST':
				// note that the POST assumes json data... no xml support yet
				$handle->setopt_array(array(
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => json_encode($data)
				));
			break;
			case 'PUT':
				$handle->setopt_array(array(
					CURLOPT_PUT => true,
					CURLOPT_INFILE => fopen($data, 'r')
				));
			break;
			case 'DELETE':
				$handle->setopt(CURLOPT_CUSTOMREQUEST, 'DELETE');
			break;
		}
		
		$return = $handle->exec();
		$response = $handle->result();
		$response = json_decode($response, true);

		return array(
			'code' => $handle->status(),
			'data' => $response,
		);
	}
}
?>