<?php

namespace OCA\Files_Sharing\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class DeletedShareAPIController extends OCSController {

	/** @var ShareManager */
	private $shareManager;

	/** @var string */
	private $userId;

	/** @var IUserManager */
	private $userManager;

	public function __construct(string $appName,
								IRequest $request,
								ShareManager $shareManager,
								string $UserId,
								IUserManager $userManager) {
		parent::__construct($appName, $request);

		$this->shareManager = $shareManager;
		$this->userId = $UserId;
		$this->userManager = $userManager;
	}

	private function formatShare(IShare $share): array {
		return [
			'id' => $share->getFullId(),
			'uid_owner' => $share->getShareOwner(),
			'displayname_owner' => $this->userManager->get($share->getShareOwner())->getDisplayName(),
			'path' => $share->getTarget(),
		];
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		$shares = $this->shareManager->getSharedWith($this->userId, \OCP\Share::SHARE_TYPE_GROUP, null, -1, 0);

		// Only get deleted shares
		$shares = array_filter($shares, function(IShare $share) {
			return $share->getPermissions() === 0;
		});

		// Only get shares where the owner still exists
		$shares = array_filter($shares, function (IShare $share) {
			return $this->userManager->userExists($share->getShareOwner());
		});

		$shares = array_map(function (IShare $share) {
			return $this->formatShare($share);
		}, $shares);

		return new DataResponse($shares);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @throws OCSException
	 */
	public function undelete(string $id): DataResponse {
		try {
			$share = $this->shareManager->getShareById($id, $this->userId);
		} catch (ShareNotFound $e) {
			throw new OCSNotFoundException('Share not found');
		}

		if ($share->getPermissions() !== 0) {
			throw new OCSNotFoundException('No deleted share found');
		}

		try {
			$this->shareManager->restoreShare($share, $this->userId);
		} catch (GenericShareException $e) {
			throw new OCSException('Something went wrong');
		}

		return new DataResponse([]);
	}
}
