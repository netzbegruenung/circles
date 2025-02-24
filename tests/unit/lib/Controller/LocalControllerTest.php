<?php
/**
 * @copyright Copyright (c) 2022 Thomas Citharel <nextcloud@tcit.fr>
 *
 * @author Thomas Citharel <nextcloud@tcit.fr>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Circles\Tests\Controller;

use OCA\Circles\AppInfo\Application;
use OCA\Circles\Controller\LocalController;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Probes\BasicProbe;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Service\CircleService;
use OCA\Circles\Service\ConfigService;
use OCA\Circles\Service\FederatedUserService;
use OCA\Circles\Service\MemberService;
use OCA\Circles\Service\MembershipService;
use OCA\Circles\Service\PermissionService;
use OCA\Circles\Service\SearchService;
use OCA\Circles\Tools\Traits\TDeserialize;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class LocalControllerTest extends TestCase {
	use TDeserialize;

	/** @var IRequest|MockObject */
	private $request;

	/** @var IUserSession|MockObject */
	private $userSession;

	/** @var FederatedUserService|MockObject */
	private $federatedUserService;

	/** @var CircleService|MockObject */
	private $circleService;

	/** @var MemberService|MockObject */
	private $memberService;

	/** @var MembershipService|MockObject */
	private $membershipService;

	/** @var SearchService|MockObject */
	private $searchService;

	/** @var PermissionService|MockObject */
	private $permissionService;

	/** @var ConfigService|MockObject */
	private $configService;

	/** @var LocalController */
	private $localController;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->federatedUserService = $this->createMock(FederatedUserService::class);
		$this->circleService = $this->createMock(CircleService::class);
		$this->memberService = $this->createMock(MemberService::class);
		$this->membershipService = $this->createMock(MembershipService::class);
		$this->searchService = $this->createMock(SearchService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->configService->expects($this->any())->method('getAppValueBool')->with(ConfigService::FRONTEND_ENABLED)->willReturn(true);
		$this->localController = new LocalController(Application::APP_ID,
													 $this->request,
													 $this->userSession,
													 $this->federatedUserService,
													 $this->circleService,
													 $this->memberService,
													 $this->membershipService,
													 $this->permissionService,
													 $this->searchService,
													 $this->configService);
	}

	/**
	 * @dataProvider dataForCirclesList
	 *
	 * @param int $limit
	 * @param int $offset
	 * @return void
	 * @throws OCSException
	 */
	public function testCirclesList(int $limit, int $offset): void {
		$probe = new CircleProbe();
		$probe->filterHiddenCircles()
			  ->filterBackendCircles()
			  ->addDetail(BasicProbe::DETAILS_POPULATION)
			  ->setItemsOffset($offset)
			  ->setItemsLimit($limit);
		$circle1 = new Circle();
		$circle1->setName('Circle One');
		$circle1->setSingleId('CircleOne');
		$circle2 = new Circle();
		$circle2->setName('Circle Two');
		$circle2->setSingleId('CircleTwo');
		$circle3 = new Circle();
		$circle3->setName('Circle Three');
		$circle3->setSingleId('CircleThree');
		$circles = [$circle1, $circle2, $circle3];
		$selectedCircles = array_slice($circles, $offset, $limit > 0 ? $limit : null);
		$this->circleService->expects($this->once())->method('getCircles')->with($probe)->willReturn($selectedCircles);
		$response = new DataResponse($this->serializeArray($selectedCircles));
		$this->assertEquals($response, $this->localController->circles($limit, $offset));
	}

	public function dataForCirclesList(): array {
		return [
			[-1, 0],
			[1, 1]
		];
	}
}
