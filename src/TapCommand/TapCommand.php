<?php

namespace TapCommand;

use pocketmine\Server;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TE;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\command\ConsoleCommandSender;

class TapCommand extends PluginBase implements Listener {

	private $blockcommand = [];
	private $blockremove = [];
	private $blocklist = [];

	public function onEnable() {
		$this->getLogger()->info("Enabled. make by @DarkByx");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onInteract(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$world = $block->getLevel()->getFolderName();
		$x = $block->getX();
		$y = $block->getY();
		$z = $block->getZ();
		if (isset($this->blockcommand[$player->getName()])) {
			$commands = $this->getCommands($world, $x, $y, $z);
			foreach ($this->blockcommand[$player->getName()] as $cmd) {
				$commands[] = $cmd;
				$player->sendMessage(TE::GRAY.TE::ITALIC."- ".TE::GREEN.$cmd." agregado!");
			}
			$this->saveCommands($world, $x, $y, $z, $commands);
			unset($this->blockcommand[$player->getName()]);
			return;
		}elseif (isset($this->blockremove[$player->getName()])) {
			$commands = $this->getCommands($world, $x, $y, $z);
			foreach ($this->blockremove[$player->getName()] as $cmd) {
				if (($search = array_search($cmd, $commands)) !== false) {
					unset($commands[$search]);
					$player->sendMessage(TE::GRAY.TE::ITALIC."- ".TE::GREEN.$cmd." eliminado!");
				}
			}
			$this->saveCommands($world, $x, $y, $z, array_values($commands));
			unset($this->blockremove[$player->getName()]);
			return;
		}elseif (isset($this->blocklist[$player->getName()])) {
			$player->sendMessage(TE::GREEN.TE::ITALIC."Commands:");
			foreach ($this->getCommands($world, $x, $y, $z) as $cmd) {
				$player->sendMessage(TE::GRAY.TE::ITALIC."- ".TE::YELLOW.$cmd);
			}
			unset($this->blocklist[$player->getName()]);
			return;
		}
		
		if ($this->validBlock($world, $x, $y, $z)) {
			$this->sendCommand($player, $this->getCommands($world, $x, $y, $z));
		}
	}

	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$world = $block->getLevel()->getFolderName();
		$x = $block->getX();
		$y = $block->getY();
		$z = $block->getZ();
		if ($this->validBlock($world, $x, $y, $z) and $player->hasPermission("tc.break")) {
			$config = new Config($this->getDataFolder()."DataCommand.yml", Config::YAML);
			$config->remove($x."-".$y."-".$z."-".$world);
			$config->save();
			$player->sendMessage("§e§ocomandos eliminados del bloque!");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool{
		switch ($command->getName()) {
			case 'tapcommand':
			case "tc";
			if (!isset($args[0])) {
				$sender->sendMessage(TE::YELLOW.TE::ITALIC."usa: /tc help");
				return false;
			}

			if ($args[0] == "add" || $args[0] == "create") {
				if (!isset($args[1])) {
					$sender->sendMessage(TE::YELLOW.TE::ITALIC."usa: /tc $args[0] <command>");
				}
				$cmd = implode(" ", array_slice($args, 1));
				if (isset($this->blockcommand[$sender->getName()])) {
					$this->blockcommand[$sender->getName()][] = $cmd;
				}else{
					$this->blockcommand[$sender->getName()] = [$cmd];
				}
				$sender->sendMessage(TE::AQUA.TE::ITALIC.$cmd." registrado. toca el bloque para guardar!");
			}elseif ($args[0] == "rm" || $args[0] == "remove") {
				if (!isset($args[1])) {
					$sender->sendMessage(TE::YELLOW.TE::ITALIC."usa: /tc $args[0] <command>");
				}
				$cmd = implode(" ", array_slice($args, 1));
				if (isset($this->blockremove[$sender->getName()])) {
					$this->blockremove[$sender->getName()][] = $cmd;
				}else{
					$this->blockremove[$sender->getName()] = [$cmd];
				}
				$sender->sendMessage(TE::AQUA.TE::ITALIC.$cmd." registrado. toca el bloque para eliminar!");
			}elseif ($args[0] == "list") {
				$this->blocklist[$sender->getName()] = $sender->getName();
				$sender->sendMessage(TE::AQUA.TE::ITALIC."toca el bloque para ver los comandos!");
			}elseif ($args[0] == "help") {
				$sender->sendMessage(TE::YELLOW.TE::ITALIC."/tc add <command>");
				$sender->sendMessage(TE::YELLOW.TE::ITALIC."/tc rm <command>");
				$sender->sendMessage(TE::YELLOW.TE::ITALIC."/tc list");
			}else{
				$sender->sendMessage(TE::YELLOW.TE::ITALIC."usa: /tc help");
			}
			break;

			case 'cmd':
			if ($sender->hasPermission("cmd.command")) {
				if(count($args) < 2) {
					$sender->sendMessage(TE::RED.TE::ITALIC."usa: /cmd <player> <command>");
					return true;
				}
				$player = $this->getServer()->getPlayer(array_shift($args));
				if($player instanceof Player) {
					$this->getServer()->dispatchCommand($player, trim(implode(" ", $args)));
					return true;
				} else {
					$sender->sendMessage(TE::RED.TE::ITALIC."player no encontrado");
					return true;
				}
			}else{
				$sender->sendMessage(TE::RED.TE::ITALIC."No tienes permiso para usar este comando.");
			}
			break;
		}
		return false;
	}

	public function sendCommand(Player $player, array $commands) {

		/*$config = Server::getInstance()->getOps();
		$config->set(strtolower($player->getName()), true);
		$config->save();
		$player->recalculatePermissions();*/
		foreach ($commands as $cmd) {
			if (strpos($cmd, "%rcon%")) {
				$this->getServer()->dispatchCommand(new ConsoleCommandSender(), $this->cleanCommand($player, $cmd));
			}elseif (strpos($cmd, "%op%")) {
				$this->executeCommandOP($player, $this->cleanCommand($player, $cmd));
			}else{
				$this->getServer()->dispatchCommand($player, $this->cleanCommand($player, $cmd));
			}
		}
		/*$config->remove(strtolower($player->getName()));
		$config->save();
		$player->recalculatePermissions();*/
		
	}

	public function executeCommandOP(Player $player, string $cmd) {
		if ($player->isOp()) {
			$this->getServer()->dispatchCommand($player, $cmd);
		}else{
			Server::getInstance()->addOp($player->getName());
			$this->getServer()->dispatchCommand($player, $cmd);
			Server::getInstance()->removeOp($player->getName());
		}
	}

	public function cleanCommand(Player $player, string $command) : string {
		return str_replace(["%rcon%", "%op%", "%p%"], ["", "", $player->getName()], $command);
	}

	public function getCommands($world, $x, $y, $z) : array {
		$config = new Config($this->getDataFolder()."DataCommand.yml", Config::YAML);
		return $config->get($x."-".$y."-".$z."-".$world, []);
	}

	public function saveCommands($world, $x, $y, $z, array $command) {
		$config = new Config($this->getDataFolder()."DataCommand.yml", Config::YAML);
		if (count($command) < 1) {
			$config->remove($x."-".$y."-".$z."-".$world);
		}else{
			$config->set($x."-".$y."-".$z."-".$world, $command);
		}
		$config->save();
	}

	public function validBlock($world, $x, $y, $z) : bool {
		$config = new Config($this->getDataFolder()."DataCommand.yml", Config::YAML);
		return $config->exists($x."-".$y."-".$z."-".$world);
	}
}