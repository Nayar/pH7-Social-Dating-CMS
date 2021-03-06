<?php
/**
 * @title          Rating Ajax Class
 * @desc           Simple Rating Page Class with Ajax.
 *
 * @author         Pierre-Henry Soria <ph7software@gmail.com>
 * @copyright      (c) 2012-2018, Pierre-Henry Soria. All Rights Reserved.
 * @license        GNU General Public License; See PH7.LICENSE.txt and PH7.COPYRIGHT.txt in the root directory.
 * @package        PH7 / App / System / Core / Asset / Ajax
 * @version        1.2
 */

namespace PH7;

defined('PH7') or exit('Restricted access');

use PH7\Framework\Cookie\Cookie;
use PH7\Framework\Http\Http;
use PH7\Framework\Mvc\Request\Http as HttpRequest;
use PH7\Framework\Session\Session;

class RatingCoreAjax
{
    /** @var HttpRequest */
    private $oHttpRequest;

    /** @var RatingCoreModel */
    private $oRatingModel;

    /** @var string */
    private $sTxt;

    /** @var string */
    private $sTable;

    /** @var int */
    private static $iVotes;

    /** @var int */
    private $iStatus;

    /** @var int */
    private $iId;

    /** @var float */
    private $fScore;

    public function __construct()
    {
        $this->oHttpRequest = new HttpRequest;

        if ($this->oHttpRequest->postExists('action') &&
            $this->oHttpRequest->postExists('table') &&
            $this->oHttpRequest->postExists('score') &&
            $this->oHttpRequest->postExists('id')
        ) {
            if ($this->oHttpRequest->post('action') == 'rating') {
                // Only for the Members
                if (!UserCore::auth()) {
                    $this->iStatus = 0;
                    $this->sTxt = t('Please <b>register</b> or <b>login</b> to vote.');
                } else {
                    $this->initialize();
                }
            }
        } else {
            Http::setHeadersByCode(400);
            exit('Bad Request Error!');
        }
    }

    /**
     * Displays the votes.
     *
     * @return string
     */
    public function show()
    {
        return jsonMsg($this->iStatus, $this->sTxt);
    }

    /**
     * Initialize the methods of the class.
     *
     * @return void
     */
    protected function initialize()
    {
        $this->oRatingModel = new RatingCoreModel;
        $this->sTable = $this->oHttpRequest->post('table');
        $this->iId = (int)$this->oHttpRequest->post('id');

        if ($this->sTable == 'Members') {
            $iProfileId = (int)(new Session)->get('member_id');

            if ($iProfileId === $this->iId) {
                $this->iStatus = 0;
                $this->sTxt = t('You can not vote your own profile!');
                return;
            }
        }

        /**
         * @internal Today's IP address is also easier to change than delete a cookie, so we have chosen the Cookie instead save the IP address in the database.
         */
        $oCookie = new Cookie;
        $sCookieName = 'pHSVoting' . $this->iId . $this->sTable;
        if ($oCookie->exists($sCookieName)) {
            $this->iStatus = 0;
            $this->sTxt = t('You have already voted!');
            return;
        } else {
            $oCookie->set($sCookieName, 1, 3600 * 24 * 7); // A week
        }
        unset($oCookie);

        $this->select();
        $this->update();
        $this->iStatus = 1;
        $sVoteTxt = self::$iVotes > 1 ? t('Votes') : t('Vote');
        $this->sTxt = t('Score: %0% - %2%: %1%', number_format($this->fScore / self::$iVotes, 1), self::$iVotes, $sVoteTxt);
    }

    /**
     * Adds voting in the database and increment the static attribute to vote.
     *
     * @return void
     */
    protected function select()
    {
        $iVotes = $this->oRatingModel->getVote($this->iId, $this->sTable);
        $fRate = $this->oRatingModel->getScore($this->iId, $this->sTable);

        self::$iVotes = $iVotes += 1;
        $fScore = (float)$this->oHttpRequest->post('score');

        $this->fScore = $fRate += $fScore;
    }

    /**
     * Updates the vote in the database.
     *
     * @return void
     */
    protected function update()
    {
        $this->oRatingModel->updateVotes($this->iId, $this->sTable);
        $this->oRatingModel->updateScore($this->fScore, $this->iId, $this->sTable);
    }
}

echo (new RatingCoreAjax)->show();
