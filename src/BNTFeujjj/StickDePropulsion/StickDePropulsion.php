<?php

namespace BNTFeujjj\StickDePropulsion;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;

class StickDePropulsion extends PluginBase implements Listener
{
    private array $cooldown = [];

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
    }

    public function onItemUse(PlayerItemUseEvent $event)
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $config = $this->getConfig();

        $configuredItem = StringToItemParser::getInstance()->parse($config->get("item"));
        if ($configuredItem === null) {
            $player->sendMessage("§cItem configuré invalide !");
            return;
        }
        if (!$item->equals($configuredItem, false, false)) {
            return;
        }

        if ($this->projete($player)) {
            $this->sendOnUseMessage($player);
        }
    }

    public function projete(Player $player): bool
    {
        $config = $this->getConfig();
        $entities = [];
    
        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            if ($player->getName() === $onlinePlayer->getName()) continue;
            if ($player->getPosition()->distance($onlinePlayer->getPosition()->asVector3()) > $config->get("radius")) continue;
            $entities[] = $onlinePlayer;
        }
    
        if (count($entities) === 0) {
            $player->sendMessage($config->get("noplayer-message"));
            return false;
        }
    
        if (isset($this->cooldown[$player->getName()]) && $this->cooldown[$player->getName()] > time()) {
            $cooldown = $this->cooldown[$player->getName()] - time();
            $player->sendMessage(str_replace("{cooldown}", $cooldown, $config->get("cooldown-message")));
            return false;
        }
    
        $height = (float) $config->get("puissance", 1.5);
    
        foreach ($entities as $entity) {
            $vector = $this->calculateVelocity($player->getLocation(), $entity->getLocation(), 6);
            $motion = clone $entity->getMotion();
            $motion->x += $vector->x * 3;
            $motion->y = $height; 
            $motion->z += $vector->z * 3;
            $entity->setMotion($motion);
        }
    
        $this->cooldown[$player->getName()] = time() + $config->get("cooldown");
        return true;
    }
    

    public function sendOnUseMessage(Player $player): void
    {
        $config = $this->getConfig();
        $message = $config->get("on-use");
        $type = $config->get("type-oneuse");

        switch ($type) {
            case "popup":
                $player->sendPopup($message);
                break;
            case "chat":
                $player->sendMessage($message);
                break;
            case "broadcast":
                Server::getInstance()->broadcastMessage($message);
                break;
        }
    }

    public function calculateVelocity(Vector3 $from, Vector3 $to, int $gain): Vector3
    {
        $nf = new Vector3(0, 0, 0);
        $nt = $to->subtract($from->getX(), $from->getY(), $from->getZ());
        $flat = new Vector3($nt->getX(), 0.0, $nt->getZ());
        $flatDist = $nt->length();
        $midPoint = $this->midPoint($nf, $nt);
        $forceV = $this->iterateGrav(abs($midPoint->getY() * 2.0) + $gain);
        $forceH = $this->iterateTrajectory($flatDist);
        $vVector = new Vector3(0.0, $forceV, 0.0);
        $hVector = $flat->normalize()->multiply($forceH);
        return $hVector->add($vVector->getX(), $vVector->getY(), $vVector->getZ());
    }

    public function midPoint(Vector3 $from, Vector3 $to): Vector3
    {
        $x = $from->getX() + ($from->getX() / 2 + $to->getX() / 2);
        $y = $from->getY() + ($from->getY() / 2 + $to->getY() / 2);
        $z = $from->getZ() + ($from->getZ() / 2 + $to->getZ() / 2);
        return new Vector3($x, $y, $z);
    }

    public function iterateTrajectory($dist): float
    {
        $currentDist = 0.0;
        $currentForce = 0.2;
        while ($currentDist < $dist) {
            $currentDist += $currentForce;
            $currentForce *= 1.08;
        }
        return $currentForce;
    }

    public function iterateGrav($dist): float
    {
        $currentDist = 0.0;
        $currentForce = 0.0;
        while ($currentDist < $dist) {
            $currentDist += $currentForce;
            $currentForce += 0.05;
            $currentForce *= 1.08;
        }
        return $currentForce;
    }
}
