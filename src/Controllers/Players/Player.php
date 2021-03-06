<?php

namespace reClick\Controllers\Players;

use reClick\Controllers\BaseController;
use reClick\Controllers\PlayersInGames\PlayersInGames;
use reClick\Traits\Cryptography;
use reClick\Models\Players\PlayerModel;

class Player extends BaseController {

    use Cryptography;

    const HASH_PASSWORD = true;
    const RAW_PASSWORD = false;

    /**
     * @var \reClick\Controllers\PlayersInGames\PlayersInGames
     */
    private $playersInGames;

    /**
     * @param int|string $identifier Player's ID|Username
     */
    public function __construct($identifier) {
        $this->model = new PlayerModel();

        if (is_numeric($identifier) && floor($identifier) == $identifier) {
            parent::__construct($identifier);
        } else {
            parent::__construct($this->model->getIdFromUsername($identifier));
        }

        $this->playersInGames = new PlayersInGames();
    }

    /**
     * @param string $username
     * @return int|string
     */
    public function username($username = null) {
        return $this->model->username($this->id, $username);
    }

    /**
     * @param string $password
     * @param bool $hashPassword HASH_PASSWORD | RAW_PASSWORD
     * @return int|string
     */
    public function password($password = null, $hashPassword = self::RAW_PASSWORD) {
        if (isset($password) && $hashPassword) {
            $password = $this->hashPassword($password);
        }

        return $this->model->password($this->id, $password);
    }

    /**
     * @param string $nickname
     * @return int|string
     */
    public function nickname($nickname = null) {
        return $this->model->nickname($this->id, $nickname);
    }

    /**
     * @param string $location
     * @return int|string
     */
    public function location($location = null) {
        return $this->model->location($this->id, $location);
    }

    /**
     * @param string $gcmRegId
     * @return int|string
     */
    public function gcmRegId($gcmRegId = null) {
        return $this->model->gcmRegId($this->id, $gcmRegId);
    }

    /**
     * @return bool
     */
    public function exists() {
        $s = $this->model->exists($this->id);
        if (empty($s)) {
            return false;
        }
        return true;
    }

    /**
     * @param int $gameId
     * @return bool
     */
    public function existsInGame($gameId) {
        $games = $this->games();
        foreach ($games as $game) {
            if ($game['id'] == $gameId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $gameId
     * @return string
     */
    public function turnInGame($gameId) {
        return $this->playersInGames->getPlayerTurn($this->id, $gameId);
    }

    /**
     * @return array
     */
    public function games() {
        return $this->playersInGames->games($this->id);
    }

    /**
     * @param int $gameId
     * @return bool
     */
    public function alreadyConfirmed($gameId) {
        return $this->playersInGames->getConfirmation($this->id, $gameId)
            ? true : false;
    }

    /**
     * @return array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'username' => $this->username(),
            'nickname' => $this->nickname(),
            'location' => $this->location()
        ];
    }
} 