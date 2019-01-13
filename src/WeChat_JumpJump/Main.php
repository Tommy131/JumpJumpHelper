<?php

namespace WeChat_JumpJump;

use pocketmine\utils\Config;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;


class Main extends PluginBase
{
	public $config;
	
	private $PRESS_TIME, $PRESS_EXP, $SLEEP_TIME_MIN, $SLEEP_TIME_MAX, $BODY_WIDTH;
	
	public function onLoad()
	{
		if(!extension_loaded("php_gd"))
		{
			$this->getLogger()->error("本服务器没有安装php-gd扩展, 无法启动本插件.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
	}
	
	public function onEnable()
	{
		if(!is_dir($this->getDataFolder())) mkdir($this->getDataFolder());
		define('__PATH__', $this->getDataFolder());
		
		$this->config = new Config(__PATH__."config.yml", Config::YAML, 
		[
			"按压力度参数" => 4.1,
			"按压时长"     => 0.842225,
			"睡眠时间MIN"  => 1.5,
			"睡眠时间MAX"  => 2.0,
			"角色宽度"     => 75
		]);
		
		
		/**
		 * 按压力度参数，根据实际表现进行调节
		 * 如果跳远了就调低点
		 */
		$this->PRESS_TIME = $this->config->get("按压时长");
		
		/**
		 * 按压力度参数，拟合结果
		 * !!不清楚不要改动!!
		 */
		$this->PRESS_EXP = $this->config->get("按压力度参数");
		
		/**
		 * 睡眠时间，随机延迟范围
		 */
		$this->SLEEP_TIME_MIN = $this->config->get("睡眠时间MIN");
		$this->SLEEP_TIME_MAX = $this->config->get("睡眠时间MAX");
		
		/**
		 * 角色宽度，根据需要调节
		 */
		$this->BODY_WIDTH = $this->config->get("角色宽度");
		
		$this->getLogger()->notice("§c微信跳一跳§e辅助外挂 §a加载完毕.");
		$this->getLogger()->notice("§e算法来自互联网, §6移植至PM §bBy Teaclon.");
	}
	
	
	public function onDisable()
	{
		$this->getLogger()->warning("Plugin Disabled!");
	}
	
	
	
	
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args)
	{
		switch($cmd->getName())
		{
			case "start":
				if(isset($args[0]))
				{
					if($args[0] === "kill") exec("adb kill-server");
					return true;
				}
				exec("adb start-server");
				$this->start();
			break;
		}
	}
	
	
	private function similar($rgb1, $rgb2, $value = 10)
	{
		$r1 = ($rgb1 >> 16) & 0xFF;
		$g1 = ($rgb1 >> 8) & 0xFF;
		$b1 = $rgb1 & 0xFF;
		$r2 = ($rgb2 >> 16) & 0xFF;
		$g2 = ($rgb2 >> 8) & 0xFF;
		$b2 = $rgb2 & 0xFF;
		return abs($r1 - $r2) < $value && abs($b1 - $b2) < $value && abs($g1 - $g2) < $value;
	}

	private function getStart()
	{
		global $image;
		$l_r    = 0;
		$cnt    = 0;
		$width  = imagesx($image);
		$height = imagesy($image);
		for($i = $height / 3 * 2; $i > $height / 3; $i--)
		{
			for($l = 0; $l < $width; $l++)
			{
				$c = imagecolorat($image, $l, $i);
				if($this->similar($c, 3750243, 20))
				{
					$r = $l;
					while($r+1 < $width && $this->similar(imagecolorat($image, $r+1, $i), 3750243, 20))
					{
						$r++;
					}
					if($r - $l > $this->BODY_WIDTH * 0.5)
					{
						if($r <= $l_r) return [$i, round(($l + $r) / 2)];
						else $cnt = 0;
						$l_r = $r;
					}
					$l = $r;
				}
			}
		}
		return array($x, $y);
	}

	private function getEnd()
	{
		global $image;
		global $sx, $sy;
		$l_r    = 0;
		$cnt    = 0;
		$width  = imagesx($image);
		$height = imagesy($image);
		for($i = $height / 3; $i < $sx; $i++)
		{
			$demo  = imagecolorat($image, $width - 1, $i);
			for($l = 0; $l < $width; $l++)
			{
				$c = imagecolorat($image, $l, $i);
				if(!$this->similar($c, $demo))
				{
					$r = $l;
					while($r+1 < $width && !$this->similar(imagecolorat($image, $r+1, $i), $demo))
					{
						$r++;
					}
					if(abs(($l + $r) / 2 - $sy) > $this->BODY_WIDTH * 0.5)
					{
						if($r - $l > $this->BODY_WIDTH * 0.9)
						{
							if(!isset($mid)) $mid = ($l + $r) / 2;
							if($r <= $l_r)
							{
								$cnt ++;
								if ($cnt == 3) return [$i, round($mid)];
							}
							else  $cnt = 0;
							$l_r = $r;
						}
					}
					$l = $r;
				}
			}
		}

		return [$sx - round(abs($mid-$sy)/sqrt(3)), round($mid)];;
	}

	private function screencap()
	{
		ob_start();
		system('adb shell screencap -p /sdcard/Pictures/screen.png');
		system('adb pull /sdcard/Pictures/screen.png .');
		ob_end_clean();
	}

	private function press($time)
	{
		// 随机点按下和稍微挪动抬起，模拟手指
		$px = rand(300,400);
		$py = rand(400,600);
		$ux = $px + rand(-10,10);
		$uy = $py + rand(-10,10);
		$swipe = sprintf("%s %s %s %s", $px, $py, $ux, $uy);
		system('adb shell input swipe ' . $swipe . ' ' . $time);
	}
	
	private function start()
	{
		$today = date("Y-m-d H");
		for ($id = 0; ; $id++)
		{
			echo sprintf("#%05d: ", $id);
			// 截图
			$this->screencap();
			// 获取坐标
			$image = imagecreatefrompng('screen.png');
			list($sx, $sy) = $this->getStart();
			list($tx, $ty) = $this->getEnd();
			if ($sx == 0) break;
			echo sprintf("(%d, %d) -> (%d, %d) ", $sx, $sy, $tx, $ty);
			// 图像描点
			imagefilledellipse($image, $sy, $sx, 10, 10, 0xFF0000);
			imagefilledellipse($image, $ty, $tx, 10, 10, 0xFF0000);
			if(!is_dir(__PATH__ . "screen")) mkdir(__PATH__ . "screen");
			if(!is_dir(__PATH__ . "screen/".$today)) mkdir(__PATH__ . "screen/".$today);
			imagepng($image, __PATH__ .'screen.scan.png');
			imagepng($image, sprintf(__PATH__ ."screen/".$today."/%05d.png", $id));
			// 计算按压时间
			$dist = sqrt(pow($tx - $sx, 2) + pow($ty - $sy, 2));
			// 2.5D距离修正
			$trdeg = rad2deg(asin(abs($tx - $sx) / $dist));
			$dist_fix = $dist * sin(deg2rad(150 - $trdeg));
			$time = pow($dist_fix, $this->PRESS_EXP) * $this->PRESS_TIME;
			$time = round($time);
			echo sprintf("dist: %f, dist_fix: %f, trdeg: %f, time: %f", $dist, $dist_fix, $trdeg, $time);
			$this->press($time);
			// 等待下一次截图，随机延迟
			$sleep = $this->SLEEP_TIME_MIN + (($this->SLEEP_TIME_MAX - $this->SLEEP_TIME_MIN) * rand(0, 10) * 0.1);
			echo sprintf(", sleep: %f\n",$sleep);
			sleep($sleep);
		}
	}

	
	
}
?>