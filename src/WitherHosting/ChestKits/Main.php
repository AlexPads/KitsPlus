<?php

declare(strict_types=1);

namespace WitherHosting\ChestKits;

use DaPigGuy\PiggyCustomEnchants\CustomEnchantManager;
use jojoe77777\FormAPI\SimpleForm;
use onebone\economyapi\EconomyAPI;
use pocketmine\{Player};
use pocketmine\command\{Command, CommandSender, ConsoleCommandSender};
use pocketmine\item\{Item, ItemFactory};
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\nbt\{tag\CompoundTag, tag\ListTag};
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;

class Main extends PluginBase
{

    public static $coolDown = [];
    public static $prefix;
    public static $c;
    public static $nbt;

    public function onEnable(): void
    {
        $this->saveDefaultConfig();
        //Set Config for Messages Prefix
        $c = yaml_parse_file($this->getDataFolder() . "config.yml");
        //set Prefix
        self::$prefix = $c["Prefix"];
        //set Prefix
    }


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {


        //Sets Config for NO Reloads/Restarts!
        self::$c = yaml_parse_file($this->getDataFolder() . "config.yml");
        switch ($command) {
            case "kit":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(TF::RED . "Command must be used in-game.");
                    break;
                }
                if (self::$c["ListType"] === "UI") {
                    $this->ChestKits($sender);
                }
                if (self::$c["ListType"] === "List" && empty($args)) {
                    $this->Kits($sender);
                }
                if (!empty($args) && self::$c["ListType"] === "List") {
                    $this->typeList($sender, $args);
                }
                break;
            case "kitgive":
				if (!$sender instanceof Player) {
					//$sender->sendMessage(TF::RED . "Command must be used in-game.");
					if (empty($args)) {
						$this->typeList($sender, $args);
						break;
					} else {
						if (empty($args[1])){
							$this->getLogger()->info(self::$prefix . "You must select a player!");
						}else {
							$sender = $this->getServer()->getPlayerExact($args[1]);
							if ($sender == null){
								$this->getLogger()->warning(self::$prefix . "That player does not exist!");
								return true;
							}
							$args[3] = "console";
							$this->GiveKits($sender, $args);
						}
					}
					break;
				}
                if ($sender->hasPermission("kp.kitgive")) {
                    if (empty($args)) {
                        $this->typeList($sender, $args);
                        break;
                    } else {
                    	$args[3] = "player";
                        $this->GiveKits($sender, $args);
                    }
                }else{
                    $sender->sendMessage(self::$prefix . "You dont have permission to run that command!");
                }
                break;
			case "kitlist":
				if ($sender instanceof ConsoleCommandSender){
					$this->Kits($sender);
				}
        }
        return false;
    }

    public function GiveKits(Player $player, $args){
        $kits = self::$c["Kits"];
        if (!empty($args) && count($args) >= 1){
            if (array_key_exists($args[0], $kits)){
                $kits["$args[0]"];
                if (isset($args[1])){
                    $send = $this->getServer()->getPlayerExact($args[1]);
                    $this->typeList($send, $args);
                }else{
                    $player->sendMessage(self::$prefix . self::$c["PlayerDoesNotExist"]);
                }
            }else {
            	if (!empty($args[3]) && $args[3] == "console"){
            		$this->getLogger()->info(self::$prefix . self::$c["KitNotExist"]);
            	} else {
					$player->sendMessage(self::$prefix . self::$c["KitNotExist"]);
				}
            }
        }else{
            $player->sendMessage(self::$prefix . " You may only give one kit at a time.");
        }

    }

    public function ChestKits(Player $player)
    {
        if (self::$c["ListType"] === "UI") {
            $groups = self::$c["Kits"];
            foreach ($groups as $kit) {
                $kits[] = $kit;
            }
            $form = new SimpleForm(function (Player $player, int $data = null) use ($kits): void {
                //Handles Player Closing Forum
                if ($data === null && self::$c["ExitMessage"] === True) {
                    $player->sendMessage(self::$prefix . self::$c["ExitMessageCustom"]);
                } else if ($data !== null || self::$c["ExitMessage"] === True) {
                    //Handles Exit Button Located at the Top For the time being.
                    if ($data == 0 && self::$c["ExitButton"] === True) {
                        $player->sendMessage(self::$prefix . self::$c["ExitMessageCustom"]);
                    }
                    if (self::$c["ExitButton"] === True) {
                    	// Fix back Button v1.0.6
                    	if ($data == 0 || $data == null){
                    		return;
						}else {
							$data = $data - 1;
						}
                    }

                    $kit = $kits[$data];
                    if ($player->hasPermission($kit["Permission"])) {
                        if (EconomyAPI::getInstance()->myMoney($player) > $kit["Cost"]) {
                            //Sets for Cooldown
                            $time = $this->checkCoolDown($player->getName(), $kit["CooldownTime"]);
                            if ($time != -1) {
                                $message = self::$c["OnCooldown2"];
                                $player->sendMessage(str_replace("{timeleft}", $time, $message));
                            } else if ($time == -1) {

                                //<---- Helmet ---->
                                $helmet = $kit["Helmet"];
                                $h = explode(":", $helmet);
                                $helmet = Item::get((int)$h[0], (int)$h[1], (int)$h[2]);
                                if ($h[3] !== "default") {
                                    $helmet->setCustomName($h[3]);
                                }
                                //Define Helmet with Enchants if Exist.
                                $helmet = $this->EnchantTest($h, $helmet);
                                //<---- Helmet ---->

                                //<---- Chestplate ---->
                                $chestplate = $kit["Chestplate"];
                                $p = explode(":", $chestplate);
                                $chestplate = Item::get((int)$p[0], (int)$p[1], (int)$p[2]);
                                if ($p[3] !== "default") {
                                    $chestplate->setCustomName($p[3]);
                                }
                                //Define Chestplate with Enchants if Exist.
                                $chestplate = $this->EnchantTest($p, $chestplate);
                                //<---- Chestplate ---->

                                //<---- Leggings ---->
                                $leggings = $kit["Leggings"];
                                $l = explode(":", $leggings);
                                $leggings = Item::get((int)$l[0], (int)$l[1], (int)$l[2]);
                                if ($l[3] !== "default") {
                                    $leggings->setCustomName($l[3]);
                                }
                                //Define Leggings with Enchants if Exist.
                                $leggings = $this->EnchantTest($l, $leggings);
                                //<---- Leggings ---->

                                //<---- Boots ---->
                                $boots = $kit["Boots"];
                                $b = explode(":", $boots);
                                $boots = Item::get((int)$b[0], (int)$b[1], (int)$b[2]);
                                if ($b[3] !== "default") {
                                    $boots->setCustomName($h[3]);
                                }
                                //Define Helmet with Enchants if Exist.
                                $boots = $this->EnchantTest($b, $boots);
                                //<---- Boots ---->

                                //<---- Adds items to Chest than to Inv ---->
                                if (self::$c["Type"] === "Chest") {
                                    //<---- Define $nbt again ---->
                                    self::$nbt = [$helmet->nbtSerialize(0), $chestplate->nbtSerialize(1), $leggings->nbtSerialize(2), $boots->nbtSerialize(3)];
                                    //<---- Define $nbt again ---->

                                    //<---- Items ---->
                                    $i = $kit["Items"];
                                    $count = 4;
                                    foreach ($i as $all) {
                                        $item = explode(":", $all);
                                        $in = Item::get((int)$item[0], (int)$item[1], (int)$item[2]);
                                        if ($item[3] !== "default") {
                                            $in->setCustomName($item[3]);
                                        }
                                        $in = $this->EnchantTest($item, $in);
                                        array_push(self::$nbt, $in->nbtSerialize($count));
                                        $count = $count + 1;
                                    }

                                    // Will this work?
                                    $nbt2 = new CompoundTag("BlockEntityTag", [new ListTag("Items", self::$nbt)]);
                                    $chest = ItemFactory::get(Item::CHEST, 0, 1);
                                    $chest->setNamedTagEntry($nbt2);
                                    $chest->setCustomName($kit["KitChestName"]);
                                    $player->getInventory()->addItem($chest);
                                }
                                //<---- Adds items to chest than to inv ---->

                                //<---- Adds straight to inv ---->
                                if (self::$c["Type"] === "Body") {

                                    //<---- Adds to INV ---->
                                    if (self::$c["GiveType"] === "INV") {
                                        $player->getInventory()->addItem($helmet);
                                        $player->getInventory()->addItem($chestplate);
                                        $player->getInventory()->addItem($leggings);
                                        $player->getInventory()->addItem($boots);
                                    }

                                    // WARNING will replace current armor!!
                                    //<---- Adds to Slot belongs to ---->
                                    if (self::$c["GiveType"] === "Body") {
                                        $player->getArmorInventory()->setHelmet($helmet);
                                        $player->getArmorInventory()->setChestplate($chestplate);
                                        $player->getArmorInventory()->setLeggings($leggings);
                                        $player->getArmorInventory()->setBoots($boots);
                                    }
                                    //<---- Items ---->
                                    $i = $kit["Items"];
                                    foreach ($i as $all) {
                                        $item = explode(":", $all);
                                        $in = Item::get((int)$item[0], (int)$item[1], (int)$item[2]);
                                        if ($item[3] !== "default") {
                                            $in->setCustomName($item[3]);
                                        }
                                        $in = $this->EnchantTest($item, $in);
                                        $player->getInventory()->addItem($in);
                                    }
                                }
                            }
                        } else {
                            $player->sendMessage(self::$prefix . self::$c["NoPermission"]);
                        }
                    }else{
                        $player->sendMessage(self::$prefix. self::$c["NotEnoughMoney"]);
                    }
                }
            });

            //<---- ExitButton ---->
            if (self::$c["ExitButton"] === true) {
                $form->addButton(self::$c["ExitButtonText"]);
            }
            //<---- ExitButton ---->

            foreach ($kits as $expanded) {
                if ($player->hasPermission($expanded["Permission"])) {
                    $form->addButton($expanded["KitFormName"]);
                } else if (!$player->hasPermission($expanded["Permission"]) && isset(self::$coolDown[$player->getName()])) {
                    $form->addButton($expanded["KitFormName"] . "\n" . self::$c["YouDontOwn"]);
                } else if ($player->hasPermission($expanded["Permission"]) && isset(self::$coolDown[$player->getName()])) {
                    $form->addButton($expanded["KitFormName"] . "\n" . self::$c["OnCooldown"]);
                }
            }
            $form->setTitle(self::$c["FormTitle"]);
            $player->sendform($form);
        }
    }


    public function Kits($player)
    {
        if (self::$c["ListType"] === "List" && $player instanceof Player) {
            $kits = self::$c["Kits"];
            $player->sendMessage(str_replace("{pluginprefix}", self::$prefix, self::$c["TopRowList"]));
            $count = 1;
            foreach ($kits as $name) {
                if ($player->hasPermission($name["Permission"])) {
                    if (self::$c["ListNumbers"] === true) {
                        $player->sendMessage(self::$c["ListNumberColor"] . $count . ".§r " . $name["KitFormName"]);
                        $count = $count + 1;
                    } else if (self::$c["ListNumbers"] === false) {
                        $player->sendMessage($name["KitFormName"]);
                    }
                }
            }
            $player->sendMessage(str_replace("{pluginprefix}", self::$prefix, self::$c["BottomRowList"]));
        } else {
			$kits = self::$c["Kits"];
			$this->getLogger()->info(str_replace("{pluginprefix}", self::$prefix, self::$c["TopRowList"]));
			$count = 1;
			foreach ($kits as $name) {
				$this->getLogger()->info($name["KitFormName"]);
			}
			$this->getLogger()->info(str_replace("{pluginprefix}", self::$prefix, self::$c["BottomRowList"]));
		}
    }


    public function typeList($player, $args)
    {
    	if ($player instanceof Player) {
			$kits = self::$c["Kits"];
			if (empty($args) || $args == null) {
				$player->sendMessage(self::$prefix . "You must define a kit to give!");
				return;
			} else {
				$selected = $args[0];
				$kit = $kits[$selected];
			}
		} else {
			$kits = self::$c["Kits"];
			if (empty($args) || $args == null) {
				$this->getLogger()->info(self::$prefix . "You must define a kit to give!");
				return;
			} else {
				$player = $this->getServer()->getPlayerExact($args[0]);
				if (empty($args[1])){
					$this->getLogger()->warning(self:: $prefix . "You must specify a player!");
					return;
				}
				$selected = $args[1];
				$kit = $kits[$selected];
			}
		}

        //sets / tests for Cooldown
        if ($player->hasPermission($kit["Permission"])) {
            if (EconomyAPI::getInstance()->myMoney($player) > $kit["Cost"]) {
                $time = $this->checkCoolDown($player->getName(), $kit["CooldownTime"]);
                if ($time != -1) {
                    $message = self::$c["OnCooldown2"];
                    $player->sendMessage(str_replace("{timeleft}", $time, $message));
                } else if ($time == -1) {
                    if (isset($kits[$args[0]])) {

                        //<---- Helmet ---->
                        $helmet = $kit["Helmet"];
                        $h = explode(":", $helmet);
                        $helmet = Item::get((int)$h[0], (int)$h[1], (int)$h[2]);
                        if ($h[3] !== "default") {
                            $helmet->setCustomName($h[3]);
                        }
                        //Define Helmet with Enchants if Exist.
                        $helmet = $this->EnchantTest($h, $helmet);
                        //<---- Helmet ---->

                        //<---- Chestplate ---->
                        $chestplate = $kit["Chestplate"];
                        $p = explode(":", $chestplate);
                        $chestplate = Item::get((int)$p[0], (int)$p[1], (int)$p[2]);
                        if ($p[3] !== "default") {
                            $chestplate->setCustomName($p[3]);
                        }
                        //Define Chestplate with Enchants if Exist.
                        $chestplate = $this->EnchantTest($p, $chestplate);
                        //<---- Chestplate ---->

                        //<---- Leggings ---->
                        $leggings = $kit["Leggings"];
                        $l = explode(":", $leggings);
                        $leggings = Item::get((int)$l[0], (int)$l[1], (int)$l[2]);
                        if ($l[3] !== "default") {
                            $leggings->setCustomName($l[3]);
                        }
                        //Define Leggings with Enchants if Exist.
                        $leggings = $this->EnchantTest($l, $leggings);
                        //<---- Leggings ---->

                        //<---- Boots ---->
                        $boots = $kit["Boots"];
                        $b = explode(":", $boots);
                        $boots = Item::get((int)$b[0], (int)$b[1], (int)$b[2]);
                        if ($b[3] !== "default") {
                            $boots->setCustomName($h[3]);
                        }
                        //Define Helmet with Enchants if Exist.
                        $boots = $this->EnchantTest($b, $boots);
                        //<---- Boots ---->

                        //<---- Adds items to Chest than to Inv ---->
                        if (self::$c["Type"] === "Chest") {
                            //<---- Define $nbt again ---->
                            self::$nbt = [$helmet->nbtSerialize(0), $chestplate->nbtSerialize(1), $leggings->nbtSerialize(2), $boots->nbtSerialize(3)];
                            //<---- Define $nbt again ---->

                            //<---- Items ---->
                            $i = $kit["Items"];
                            $count = 4;
                            foreach ($i as $all) {
                                $item = explode(":", $all);
                                $in = Item::get((int)$item[0], (int)$item[1], (int)$item[2]);
                                if ($item[3] !== "default") {
                                    $in->setCustomName($item[3]);
                                }
                                $in = $this->EnchantTest($item, $in);
                                array_push(self::$nbt, $in->nbtSerialize($count));
                                $count = $count + 1;
                            }

                            // Will this work?
                            $nbt2 = new CompoundTag("BlockEntityTag", [new ListTag("Items", self::$nbt)]);
                            $chest = ItemFactory::get(Item::CHEST, 0, 1);
                            $chest->setNamedTagEntry($nbt2);
                            $chest->setCustomName($kit["KitChestName"]);
                            $player->getInventory()->addItem($chest);
                        }
                        //<---- Adds items to chest than to inv ---->

                        //<---- Adds straight to inv ---->
                        if (self::$c["Type"] === "Body") {

                            //<---- Adds to INV ---->
                            if (self::$c["GiveType"] === "INV") {
                                $player->getInventory()->addItem($helmet);
                                $player->getInventory()->addItem($chestplate);
                                $player->getInventory()->addItem($leggings);
                                $player->getInventory()->addItem($boots);
                            }

                            // WARNING will replace current armor!!
                            //<---- Adds to Slot belongs to ---->
                            if (self::$c["GiveType"] === "Body") {
                                $player->getArmorInventory()->setHelmet($helmet);
                                $player->getArmorInventory()->setChestplate($chestplate);
                                $player->getArmorInventory()->setLeggings($leggings);
                                $player->getArmorInventory()->setBoots($boots);
                            }
                            //<---- Items ---->
                            $i = $kit["Items"];
                            foreach ($i as $all) {
                                $item = explode(":", $all);
                                $in = Item::get((int)$item[0], (int)$item[1], (int)$item[2]);
                                if ($item[3] !== "default") {
                                    $in->setCustomName($item[3]);
                                }
                                $in = $this->EnchantTest($item, $in);
                                $player->getInventory()->addItem($in);
                            }
                        }
                        //<---- Giving Message ---->
                        $player->sendMessage(str_replace("{kit}", $kit["KitFormName"], self::$prefix . self::$c["GivenKitMessage"]));
                        //<---- Giving Message ---->

                    } else {
                        $player->sendMessage(self::$prefix . self::$c["KitNotExist"]);
                    }
                }
            } else {
                $player->sendMessage(self::$prefix . self::$c["NotEnoughMoney"]);
            }
        }else{
            $player->sendMessage(self::$prefix. self::$c["NoPermission"]);
        }
    }


    public function EnchantTest($item, Item $in)
    {
        // Starts the Probably broken Enchant Detection
        if (isset($item[4])) {
            $count = 0;
            $lcount = 0;
            $em = explode(",", $item[4]);
            foreach ($em as $e) {
                if (is_numeric($e)) {
                    $enc = Enchantment::getEnchantment((int)$e);
                    $count = $count + 1;
                    if (isset($item[5])) {
                        $l = explode(",", $item[5]);
                        $enchInstance = new EnchantmentInstance($enc, (int)$l[(int)$lcount]);
                        $in->addEnchantment($enchInstance);
                        $lcount = $lcount + 1;
                    }
                }else{
                	// $in is the item.
					// $e is the enchantment.
                    //Should Deal with PiggyCustomEnchants Update.
                    $l = explode(",", $item[5]);
					$in->addEnchantment(new EnchantmentInstance(CustomEnchantManager::getEnchantmentByName((string)$e), (int)$l[(int)$lcount]));

                    //$in->addEnchantment($id, (int)$l[(int)$lcount], true);
                    $lcount = $lcount + 1;
                }
            }
        }
        return $in;
    }


    public function checkCoolDown($name, $seconds): int
    {
        $player = $this->getServer()->getPlayerExact($name);
        if ($player->hasPermission("kit.cooldown.bypass")) {
            return -1;
        } else {
            if (!isset(self::$coolDown[$name])) {
                self::$coolDown[$name] = (time() + $seconds);
                return -1; // not on cooldown just added
            } else {
                if (time() > self::$coolDown[$name]) {
                    self::$coolDown[$name] = (time() + $seconds);
                    return -1; // cooldown is over
                }
                $time = self::$coolDown[$name] - time();
                return $time; // still on cooldown.
            }
        }
    }
}
