<?php

namespace reClick\Routes;

use reClick\Controllers\Players\Players;
use reClick\GCM\GCM;
use Slim\Slim;
use reClick\Controllers\Players\Player;
use reClick\Controllers\Games\Games;
use reClick\Framework\ResponseMessage;
use reClick\Controllers\Games\Game;

class PreGameRouter extends BaseRouter {

    public function __construct() {
        parent::__construct();
    }

    protected function initializeRoutes() {
        $this->app->get(
            '/games/',
            [$this, 'getGames']
        );

        $this->app->get(
            '/games/:gameId/',
            [$this, 'getGame']
        );

        $this->app->get(
            '/players/:username/games/',
            [$this, 'getPlayerGames']
        );

        $this->app->post(
            '/games/',
            [$this, 'createGame']
        );

        $this->app->post(
            '/games/:gameId/players/:username/',
            [$this, 'addPlayerToGame']
        );

        $this->app->post(
            '/games/:gameId/players/:username/invite/',
            [$this, 'invitePlayerToGame']
        );

        $this->app->put(
            '/games/:gameId/',
            [$this, 'updateGame']
        );

        $this->app->put(
            '/games/:gameId/players/:username/',
            [$this, 'playerConfirmed']
        );

        $this->app->post(
            '/games/:gameId/start/',
            [$this, 'startGame']
        );
    }

    /**
     * GET /games/
     */
    public function getGames() {
        $app = Slim::getInstance();

        $vars = parent::initGetVars(
            $app->request->get(),
            [],
            ['type', 'butUsername']
        );

        if (isset($vars['type']) && $vars['type'] == 'open') {
            if (isset($vars['butUsername'])) {

                $player = new Player($vars['butUsername']);
                if (!$player->exists()) {
                    (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                        ->message('Player does not exist')
                        ->send();
                    exit;
                }

                (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
                    ->addData(
                        'games',
                        (new Games())->getOpenGamesBut($player->id()))
                    ->send();
                exit;
            }

            (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
                ->addData('games', (new Games())->getOpenGames())
                ->send();
            exit;
        }

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->addData('games', (new Games())->getAllGames())
            ->send();
        exit;
    }

    /**
     * GET /games/:gameId
     *
     * @param int $gameId
     */
    public function getGame($gameId) {
        $game = new Game($gameId);

        if (!$game->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Game does not exist')
                ->send();
            exit;
        }

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->addData('game', $game->toArray())
            ->send();
    }

    /**
     * GET /players/:username/games
     *
     * @param string $username
     */
    public function getPlayerGames($username) {
        $player = new Player($username);
        if (!$player->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player does not exist')
                ->send();
            exit;
        }

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->addData('games', $player->games())
            ->send();
        exit;
    }

    /**
     * POST /games/
     */
    public function createGame() {
        $app = Slim::getInstance();

        $vars = parent::initJsonVars(
            $app->request->getBody(),
            ['username'],
            ['gameName', 'gameDescription']
        );

        $player = new Player($vars['username']);
        if (!$player->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player does not exist')
                ->send();
            exit;
        }

        $game = (new Games())->create(
            $vars['gameName'],
            $vars['gameDescription']
        );
        $game->addPlayer($player->id());
        $game->playerConfirmed($player->id());

        $gameId = $game->id();
        $gameName = $game->name();
        $gameDescription = $game->description();
        $gameSequence = $game->sequence() ? $game->sequence() : 'null';
        $gameStarted = $game->started() ? '1' : '0';

        $gcm = new GCM();
        $gcm->message()
            ->addData('type', 'gameCreatedCreatorCommand')
            ->addData('id', $gameId)
            ->addData('name', $gameName)
            ->addData('description', $gameDescription)
            ->addData('sequence', $gameSequence)
            ->addData('started', $gameStarted);
        $gcm->message()->addRegistrationId($player->gcmRegId());
        $gcm->sendMessage();

        $gcm = new GCM();
        $gcm->message()
            ->addData('type', 'gameCreatedCommand')
            ->addData('id', $gameId)
            ->addData('name', $gameName)
            ->addData('description', $gameDescription)
            ->addData('sequence', $gameSequence)
            ->addData('started', $gameStarted);
        $players = new Players();
        $players = $players->getAllPlayers();
        foreach ($players as $p) {
            if ($p['id'] != $player->id()) {
                $p = new Player($p['id']);
                $gcm->message()->addRegistrationId($p->gcmRegId());
            }
        }
        $gcm->sendMessage();

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->addData('game', $game->toArray())
            ->send();
    }

    /**
     * POST /games/:gameId/players/:username/
     *
     * @param int $gameId
     * @param string $username
     */
    public function addPlayerToGame($gameId, $username) {
        $game = new Game($gameId);
        if (!$game->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Game does not exist')
                ->send();
            exit;
        }

        $player = new Player($username);
        if (!$player->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player does not exist')
                ->send();
            exit;
        }
        if ($player->existsInGame($gameId)) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player already was invited')
                ->send();
            exit;
        }

        $game->addPlayer($player->id());
        $game->playerConfirmed($player->id());

        $playerTurn = $player->turnInGame($game->id());
        $game->turn($playerTurn);

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->message('Player joined the game')
            ->addData('game', $game->toArray())
            ->send();
    }

    /**
     * POST /games/:gameId/players/:username/invite/
     *
     * @param int $gameId
     * @param string $username
     */
    public function invitePlayerToGame($gameId, $username) {
        $game = new Game($gameId);
        if (!$game->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Game does not exist')
                ->send();
            exit;
        }

        $player = new Player($username);
        if (!$player->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player does not exist')
                ->send();
            exit;
        }
        if ($player->existsInGame($gameId)) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player already was invited')
                ->send();
            exit;
        }

        $game->addPlayer($player->id());

        $gcm = new GCM();
        $gcm->message()
            ->addData('type', "invite")
            ->addData(
                'message',
                'You\'ve been invited to '
                . $game->name() . ' game. Click here to join.'
            )
            ->addData('gameId', $game->id())
            ->addData('sequence', $game->sequence())
            ->addRegistrationId($game->currentPlayer()->gcmRegId());
        $gcm->sendMessage();

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->message('Player was invited to game')
            ->addData('game', $game->toArray())
            ->send();
    }

    /**
     * PUT /games/:gameId/
     *
     * @param int $gameId
     */
    public function updateGame($gameId) {
        $game = new Game($gameId);
        if (!$game->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Game does not exist')
                ->send();
            exit;
        }

        $app = Slim::getInstance();

        $vars = parent::initJsonVars(
            $app->request->getBody(),
            [],
            [ 'gameName', 'gameDescription']
        );

        $game->updateInfo($vars['gameName'], $vars['gameDescription']);

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->addData('game', $game->toArray())
            ->send();
    }

    /**
     * PUT /games/:gameId/players/:username/
     *
     * @param int $gameId
     * @param string $username
     */
    public function playerConfirmed($gameId, $username) {
        $game = new Game($gameId);
        if (!$game->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Game does not exist')
                ->send();
            exit;
        }

        $player = new Player($username);
        if (!$player->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player does not exist')
                ->send();
            exit;
        }

        if ($player->alreadyConfirmed($gameId)) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Player already confirmed')
                ->send();
            exit;
        }

        $game->playerConfirmed($player->id());

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->message('Player joined the game')
            ->addData('game', $game->toArray())
            ->send();
    }

    /**
     * PUT /games/:gameId/start/
     *
     * @param int $gameId
     */
    public function startGame($gameId) {
        $game = new Game($gameId);

        if (!$game->exists()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Game does not exist')
                ->send();
            exit;
        }

        if ($game->started()) {
            (new ResponseMessage(ResponseMessage::STATUS_FAIL))
                ->message('Game already started')
                ->send();
            exit;
        }

        $game->start();

        (new ResponseMessage(ResponseMessage::STATUS_SUCCESS))
            ->addData('game', $game->toArray())
            ->send();
    }
}