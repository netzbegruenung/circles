<?php


namespace OCA\Circles\Activity;


use Exception;
use InvalidArgumentException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\SharingFrame;
use OCA\Circles\Service\CirclesService;
use OCA\Circles\Service\MiscService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;


class Provider implements IProvider {

	/** @var MiscService */
	protected $miscService;

	/** @var IL10N */
	protected $l10n;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	public function __construct(
		IURLGenerator $url, IManager $activityManager, IL10N $l10n, MiscService $miscService
	) {
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->l10n = $l10n;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $lang
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 *
	 * @return IEvent
	 * @since 11.0.0
	 */
	public function parse($lang, IEvent $event, IEvent $previousEvent = null) {

		if ($event->getApp() !== 'circles') {
			throw new \InvalidArgumentException();
		}

		$event = $this->parseAsMember($lang, $event);
		$event = $this->parseAsModerator($lang, $event);
//		$event = $this->parsePopulation($lang, $event);
//		$event = $this->parseRights($lang, $event);
//		$event = $this->parseShares($lang, $event);

		return $event;
	}


	/**
	 * @param string $lang
	 * @param IEvent $event
	 *
	 * @return IEvent
	 * @throws Exception
	 */
	private function parseAsMember($lang, IEvent $event) {
		if ($event->getType() !== 'circles_as_member') {
			return $event;
		}

		$params = $event->getSubjectParameters();
		$circle = Circle::fromJSON($this->l10n, $params['circle']);
		if ($circle === null) {
			return $event;
		}

		$event->setIcon(CirclesService::getCircleIcon($circle->getType()));
		switch ($event->getSubject()) {
			case 'circle_create':
				return $this->parseCircleCreate($lang, $circle, $event);

			case 'circle_delete':
				return $this->parseCircleDelete($lang, $circle, $event);
		}

		$event = $this->parseMembersAsMember($lang, $circle, $event);

		return $event;
	}


	/**
	 * @param $lang
	 * @param Circle $circle
	 * @param IEvent $event
	 *
	 * @return IEvent
	 */
	private function parseMembersAsMember($lang, Circle $circle, IEvent $event) {
		$params = $event->getSubjectParameters();

		$member = Member::fromJSON($this->l10n, $params['member']);
		if ($member === null) {
			return $event;
		}

		switch ($event->getSubject()) {
			case 'member_join':
				return $this->parseMemberJoin($lang, $circle, $member, $event);

			default:
				return $event;
		}

	}


	/**
	 * @param string $lang
	 * @param IEvent $event
	 *
	 * @return IEvent
	 */
	private function parseAsModerator($lang, IEvent $event) {
		if ($event->getType() !== 'circles_as_moderator') {
			return $event;
		}

		$params = $event->getSubjectParameters();
		$circle = Circle::fromJSON($this->l10n, $params['circle']);
		$member = Member::fromJSON($this->l10n, $params['member']);
		if ($member === null || $circle === null) {
			return $event;
		}

		$event->setIcon(CirclesService::getCircleIcon($circle->getType()));

		switch ($event->getSubject()) {
			case 'member_invited':
				return $this->parseMemberInvited($lang, $circle, $member, $event);

			case 'member_request_invitation':
				return $this->parseMemberRequestInvitation($lang, $circle, $member, $event);

			default:
				throw new InvalidArgumentException();
		}
	}

	/**
	 * @param string $lang
	 * @param Circle $circle
	 * @param IEvent $event
	 *
	 * @return IEvent
	 */
	private function parseCircleCreate($lang, Circle $circle, IEvent $event) {
		if ($circle->getOwner()
				   ->getUserId() === $this->activityManager->getCurrentUserId()
		) {
			$event->setRichSubject(
				$this->l10n->t(
					'You created the circle {circle}'
				),
				['circle' => $this->generateCircleParameter($circle)]
			);

		} else {
			$event->setRichSubject(
				$this->l10n->t(
					'{author} created the circle {circle}'
				), [
					'author' => $author = $this->generateUserParameter(
						$circle->getOwner()
							   ->getUserId()
					),
					'circle' => $this->generateCircleParameter($circle)
				]
			);
		}

		return $event;
	}


	/**
	 * @param string $lang
	 * @param Circle $circle
	 * @param IEvent $event
	 *
	 * @return IEvent
	 */
	private function parseCircleDelete($lang, Circle $circle, IEvent $event) {
		if ($circle->getOwner()
				   ->getUserId() === $this->activityManager->getCurrentUserId()
		) {
			$event->setRichSubject(
				$this->l10n->t(
					'You deleted circle {circle}'
				),
				['circle' => $this->generateCircleParameter($circle)]
			);
		} else {
			$event->setRichSubject(
				$this->l10n->t(
					'{author} deleted circle {circle}'
				), [
					'author' => $this->generateUserParameter(
						$circle->getOwner()
							   ->getUserId()
					),
					'circle' => $this->generateCircleParameter($circle)
				]
			);
		}

		return $event;
	}


	/**
	 * @param string $lang
	 * @param Circle $circle
	 * @param Member $member
	 * @param IEvent $event
	 *
	 * @return IEvent
	 */
	private function parseMemberInvited($lang, Circle $circle, Member $member, IEvent $event) {

		if ($circle->getUser()
				   ->getUserId() === $this->activityManager->getCurrentUserId()
		) {
			$event->setRichSubject(
				$this->l10n->t(
					'You have invited {member} into {circle}'
				),
				[
					'circle' => $this->generateCircleParameter($circle),
					'member' => $this->generateMemberParameter($member)
				]
			);

		} elseif ($member->getUserId() === $this->activityManager->getCurrentUserId()) {
			$event->setRichSubject(
				$this->l10n->t(
					'You have been invited into {circle} by {author}'
				), [
					'author' => $this->generateUserParameter(
						$circle->getUser()
							   ->getUserId()
					),
					'circle' => $this->generateCircleParameter($circle)
				]
			);

		} else {
			$event->setRichSubject(
				$this->l10n->t(
					'{member} have been invited into {circle} by {author}'
				), [
					'author' => $this->generateUserParameter(
						$circle->getUser()
							   ->getUserId()
					),
					'circle' => $this->generateCircleParameter($circle),
					'member' => $this->generateMemberParameter($member)
				]
			);
		}

		return $event;
	}


	/**
	 * @param $lang
	 * @param Circle $circle
	 * @param Member $member
	 * @param IEvent $event
	 *
	 * @return IEvent
	 */
	private function parseMemberRequestInvitation(
		$lang, Circle $circle, Member $member, IEvent $event
	) {
		if ($member->getUserId() === $this->activityManager->getCurrentUserId()) {
			$event->setRichSubject(
				$this->l10n->t(
					'You have requested an invitation to {circle}'
				),
				[
					'circle' => $this->generateCircleParameter($circle)
				]
			);

		} else {
			$event->setRichSubject(
				$this->l10n->t(
					'{author} have requested an invitation into {circle}'
				), [
					'author' => $this->generateMemberParameter($member),
					'circle' => $this->generateCircleParameter($circle)
				]
			);
		}

		return $event;
	}


	/**
	 * @param $lang
	 * @param Circle $circle
	 * @param Member $member
	 * @param IEvent $event
	 *
	 * @return IEvent
	 */
	private function parseMemberJoin($lang, Circle $circle, Member $member, IEvent $event) {
		return $event;
	}

//
//
//	/**
//	 * @param string $lang
//	 * @param IEvent $event
//	 *
//	 * @return IEvent
//	 */
//	private function parsePopulation($lang, IEvent $event) {
//		if ($event->getType() !== 'circles_population') {
//			return $event;
//		}
//
//		return $event;
//	}
//
//
//	/**
//	 * @param string $lang
//	 * @param IEvent $event
//	 *
//	 * @return IEvent
//	 */
//	private function parseRights($lang, IEvent $event) {
//		if ($event->getType() !== 'circles_rights') {
//			return $event;
//		}
//
//		return $event;
//	}
//
//
//	/**
//	 * @param string $lang
//	 * @param IEvent $event
//	 *
//	 * @return IEvent
//	 */
//	private function parseShares($lang, IEvent $event) {
//		if ($event->getType() !== 'circles_shares') {
//			return $event;
//		}
//
//		return $event;
//	}
//
//	private function parseMood(IEvent &$event, $mood) {
//
//		if (key_exists('website', $mood)) {
//			$event->setRichMessage(
//				$mood['text'] . '{opengraph}',
//				['opengraph' => $this->generateOpenGraphParameter('_id_', $mood['website'])]
//			);
//		} else {
//			$event->setParsedMessage($mood['text']);
//		}
//
//	}


	private function parseActivityHeader(IEvent &$event, SharingFrame $frame) {

		$this->activityManager->getCurrentUserId();

		if ($frame->getAuthor() === $this->activityManager->getCurrentUserId()
			&& $frame->getCloudId() === null
		) {

			$event->setParsedSubject(
				$this->l10n->t(
					'You shared a mood with %1$s', ['circle1, circle2']
				)
			)
				  ->setRichSubject(
					  $this->l10n->t(
						  'You shared a mood with {circles}'
					  ),
					  ['circles' => $this->generateCircleParameter($frame)]

				  );

		} else {

			$author = $this->generateUserParameter($frame);
			$event->setParsedSubject(
				$this->l10n->t(
					'%1$s shared a mood with %2$s', [
													  $author['name'],
													  'circle1, circle2'
												  ]
				)
			)
				  ->setRichSubject(
					  $this->l10n->t(
						  '{author} shared a mood with {circles}'
					  ), [
						  'author'  => $author,
						  'circles' => $this->generateCircleParameter($frame)
					  ]
				  );
		}
	}


	private function generateCircleParameter(Circle $circle) {
		return [
			'type' => 'circle',
			'id'   => $circle->getId(),
			'name' => $circle->getName(),
			'link' => \OC::$server->getURLGenerator()
								  ->linkToRoute('circles.Navigation.navigate')
					  . '#' . $circle->getId()
		];
	}


	private function generateMemberParameter(Member $member) {
		return $this->generateUserParameter($member->getUserId());
	}

	/**
	 * @param $userId
	 *
	 * @return array
	 */
	private function generateUserParameter($userId) {
		return [
			'type' => 'user',
			'id'   => $userId,
			'name' => $userId
		];
	}
}
