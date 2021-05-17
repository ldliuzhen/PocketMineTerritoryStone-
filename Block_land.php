<?php
namespace Block_land;

use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use revivalpmmp\pureentities\event\CreatureSpawnEvent;           //调用PureX生物插件
use pocketmine\level\Position;

class Block_land extends PluginBase implements Listener
{
    public function setCommandStatus($int, $player){                 //[破晓新增]联动PureX，开关功能
    	//0 false
    	//1 true
    	if($int >= 0 && $int <= 2){
    		$this->status[strtolower($player)] = $int;
    	}
    }
	    
    public function getCommandStatus($player){                         //开关套件，获得开关状态
    	if(isset($this->status[strtolower($player)])){
    		return $this->status[strtolower($player)];
    	}else{
    		$this->status[strtolower($player)] = 0;
    		return $this->status[strtolower($player)];
    	}
    }

    public function endCommandSession($player){                                    //开关套件,清除开关状态
    	unset($this->status[strtolower($player)]);
    }

	public function onEnable()
	{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->Block = new Config($this->getDataFolder() . 'Block.yml',Config::YAML,['Block' => 57 , 'Land' => 10]);
		$this->Land = new Config($this->getDataFolder() . 'Land.yml',Config::YAML,[]);
		$this->centers = new Config($this->getDataFolder() . 'centers.yml',Config::YAML,[]);
		$this->record = new Config($this->getDataFolder() . 'record.yml',Config::YAML,[]);
		$this->getLogger()->info('§2[史莱姆定制]§6Block_land插件加载完成...');
		$this->getLogger()->info('§a[破晓]领地石联动PureX启动');
		$this->click = [];
		$this->del = [];
	}

	public function Interact(PlayerInteractEvent $event)                                  //将PlayerInteractEvent事件注册到$event中,可调取$event中所储存的所有API
	{
		$block = $event->getBlock();
		$name = $event->getPlayer()->getName();
		$player = $event->getPlayer();
		$if = $this->block(new Vector3($block->x,$block->y,$block->z),$block->level->getName());
		$pos_name = $this->lan(new Vector3($block->x,$block->y,$block->z),$block->level->getName());
		if($if !== False)
		{
			if($if != $name)
			{
				$hand = $event->getPlayer()->getInventory()->getItemInHand()->getID();
				if($hand == 325)    //判断是否为桶
				{
					if($this->Land->exists($pos_name) and isset($this->Land->get($pos_name)[$name])) return;
					if($player->isOp()) return;
					$event->getPlayer()->sendMessage('§2[领地石]此处为'.$if.'的领地,此处不可操作!');
					$event->setCancelled();
					return;
				}
			}
		}
		$pos_name = $block->x.$block->y.$block->z;
		$land = $this->Block->get('Land') / 2;
		if($this->Block->exists($pos_name))
		{
			$block = $this->Block->get($pos_name);
			if(isset($this->click[$name]))
			{
				if($block[5] == $name)
				{
					$list;
					$this->Land->exists($pos_name) ? $list = $this->Land->get($pos_name) : $list = [];
					$list[$this->click[$name]] = True;
					$this->Land->set($pos_name,$list);
					$this->Land->save();
					unset($this->click[$name]);
					$player->sendMessage('§2[领地石]已成功添加一个共享者!');
					return;
				}
			}
			if(isset($this->del[$name]))
			{
				if($block[5] == $name)
				{
					$list;
					$this->Land->exists($pos_name) ? $list = $this->Land->get($pos_name) : $list = [];
					if(!isset($list[$this->del[$name]])) return $player->sendMessage('§2[领地石]此领地内未找到相应的共享者!删除失败!');
					unset($list[$this->del[$name]]);
					$this->Land->set($pos_name,$list);
					$this->Land->save();
					unset($this->del[$name]);
					$player->sendMessage('§2[领地石]已成功删除一个共享者!');
					return;
				}
			}
			if($this->getCommandStatus($event->getPlayer()->getName()) == 1)             //检查指令状态
			{	
				if($this->centers->exists($pos_name)){                                          //[破晓新增]检测centers文件里是存在对应项目
					$player->sendMessage('§2[领地石]该领地石的§9生物防御§2已激活,无需再次激活');
					$this->setCommandStatus(0, $event->getPlayer()->getName());
					return;
				}
				if($event->getBlock()->getID() == $this->Block->get('Block')){
					$this->centers->set($pos_name,["x" => $event->getBlock()->x,"y" => $event->getBlock()->y,"z" => $event->getBlock()->z,"level" => $event->getBlock()->getLevel()->getName(),"radius" => $land, "拥有者" => $name]);     //将获取到的信息写入到文件中去
					$this->centers->save();
					$player->sendMessage('§2[领地石]§9生物防御§2已启动');
					$this->setCommandStatus(0, $event->getPlayer()->getName());
					return;
				}
			}
		}
	}

	public function onCommand(CommandSender $sender,Command $command,$label,array $args)//命令发送接收事件
	{
		if($command->getName() == '领地石')
		{
			if(!isset($args[0])) return;
			if($args[0] != '帮助' && $args[0] !='添加共享' && $args[0] !='删除共享' && $args[0] !='生物防御' && !isset($args[1])){
				$sender->sendMessage('§4对不起,输入错误');
				return;
			}
			$name = $sender->getName();
			if($args[0] == '帮助' && !isset($args[1])){										//破晓修改
				$sender->sendMessage("§2/领地石 添加共享 [游戏名]\n§2/领地石 删除共享 [游戏名]\n§b/领地石 生物防御");                         //破晓修改
				return;
			}
			if($args[0] == '添加共享' ) 
			{
				if(!isset($args[1]))
				{
					$sender->sendMessage('§4[领地石]错误,未定义对象!');
					return;
				}
				$this->click[$name] = $args[1];
			}
			if($args[0] == '删除共享' ) 
			{
				if(!isset($args[1]))
				{
					$sender->sendMessage('§4[领地石]错误,未定义对象!');
					return;
				}
				$this->del[$name] = $args[1];
			}
			if($args[0] == '生物防御' && !isset($args[1])) {                                   //一定要加!isset($args[1]),不然会有警告，当这个子类目下还有子类目时
				$sender->sendMessage('§2当启动生物防御时,生物将无法在领地石范围内生成,请使用/领地石 生物防御 §4启动 §2指令来激活');        //[破晓新增]联动PureX
				return;
			}
			if($args[1] == '启动') {
					$this->setCommandStatus(1, $sender->getName());                                                       //更改指令状态,使用自定义function setCommandStatus()
					$sender->sendMessage('§2[领地石]请选择需要激活的领地石');
					return;
			}
			$sender->sendMessage('§2[领地石]请点击一个领地石来完成操作!');
			return true;
		}
	}

	public function Place(BlockPlaceEvent $event)
	{
		$block = $event->getBlock();
		$name = $event->getPlayer()->getName();
		$if = $this->block(new Vector3($block->x,$block->y,$block->z),$block->level->getName());
		$pos_name = $this->lan(new Vector3($block->x,$block->y,$block->z),$block->level->getName());
		if($if !== False)
		{
			if($if != $name && !$event->getPlayer()->isOp())
			{
				if($this->Land->exists($pos_name) and isset($this->Land->get($pos_name)[$name]) and $block->getID() != $this->Block->get('Block')) return;              //[破晓修改]判断是否为共享玩家，并且放置的对象不为领地石
				$event->getPlayer()->sendMessage('§2[领地石]此处为'.$if.'的领地,不可放置!');		//破晓修改
				$event->setCancelled();
				return;
			}
			if($if != $name && $event->getPlayer()->isOp())
			{
				$pos_name = $block->x.$block->y.$block->z;
				$time = time();
				$event->getPlayer()->sendMessage('§2[领地石]此处为'.$if.'的领地,您为OP可以操作，但您的操作将被记录!');		//破晓修改
				$this->record->set($time,['操作OP' => $name,'被操作的玩家领地石归属' => $if,'被操作领地石坐标' => $pos_name,'放置的东西' => $block->getID()]);
				$this->record->save();
				return;
			}
		}
		if($block->getID() == $this->Block->get('Block'))
		{
			$pos_name = $block->x.$block->y.$block->z;
			$land = $this->Block->get('Land') / 2;
			$x1 = $block->x - $land;
			$z1 = $block->z - $land;
			$x2 = $block->x + $land;
			$z2 = $block->z + $land;
			$pos_info = [$x1,$x2,$z1,$z2,$block->level->getName(),$name];
			$this->Block->set($pos_name,$pos_info);
			$this->Block->save();
			$event->getPlayer()->sendMessage('§e领地石已上线,领地保护系统启动!');		//[破晓修改]
		}
	}

	public function Break(BlockBreakEvent $event)
	{
		$block = $event->getBlock();
		$name = $event->getPlayer()->getName();
		$if = $this->block(new Vector3($block->x,$block->y,$block->z),$block->level->getName());
		$pos_name = $this->lan(new Vector3($block->x,$block->y,$block->z),$block->level->getName());
		$note = $block->x.$block->y.$block->z;
		if($if !== False)
		{
			if($if != $event->getPlayer()->getName() && !$event->getPlayer()->isOp())
			{
				if($this->Land->exists($pos_name) and isset($this->Land->get($pos_name)[$name]) and $block->getID() != $this->Block->get('Block'))return;              //[破晓修改]判断是否为共享玩家，并且破坏的对象不为领地石
				$event->getPlayer()->sendMessage('§2[领地石]此处为'.$if.'的领地,不可破坏!');			//[破晓修改]
				$event->setCancelled();
				return;
			}
			if($if != $name && $event->getPlayer()->isOp())
				{
					$time = time();
					$event->getPlayer()->sendMessage('§2[领地石]此处为'.$if.'的领地,您为OP可以操作，但您的操作将被记录!');		//破晓修改
					$this->record->set($time,['操作OP' => $name,'被操作的玩家领地石归属' => $if,'被操作领地石坐标' => $note,'被破坏的的东西' => $block->getID()]);
					$this->record->save();
					}	
			$pos_name = $block->x.$block->y.$block->z;
			if($this->Block->exists($pos_name))
			{
				$this->Block->remove($pos_name);
				$this->Block->save();
				$event->getPlayer()->sendMessage('§e领地石被破坏，保护系统失效!');			//[破晓修改]
			}
			if($this->centers->exists($pos_name))                                          //[破晓新增]检测centers文件里是存在对应项目
				{
					$this->centers->remove($pos_name);
					$this->centers->save();
					$event->getPlayer()->sendMessage('§a领地石被破坏，生物防御系统失效!');					
				}
		}
	}

	public function block($pos,$level)
	{
		$all = $this->Block->getAll();
		foreach($all as $name => $xyz)
		{
			if($xyz[0] < $pos->x And $xyz[1] > $pos->x && $xyz[2] < $pos->z And $xyz[3] > $pos->z)
			{
				if($level == $xyz[4])
				{
					return $xyz[5];
				}
			}
		}
		return False;
	}

	public function lan($pos,$level)
	{
		$all = $this->Block->getAll();
		foreach($all as $name => $xyz)
		{
			if($xyz[0] < $pos->x And $xyz[1] > $pos->x && $xyz[2] < $pos->z And $xyz[3] > $pos->z)
			{
				if($level == $xyz[4])
				{
					return $name;
				}
			}
		}
		return False;
	}

	public function onCreatureSpawn(CreatureSpawnEvent $event)                      //[破晓新增]        //调用PureX生物插件API，CreatureSpawnEvent事件
	{
	    foreach($this->centers->getAll() as $center){
			$pos = new Position(
			$center["x"],
			$center["y"],
			$center["z"],
			$this->getServer()->getLevelByName($center["level"])
			);
			$entity = $event->getPosition();
			if(($entity->distance($pos) < $center["radius"] && $center["level"] === $event->getLevel()->getName())) {
			$event->setCancelled();
			}
	    }
    }
}
