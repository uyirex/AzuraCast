<?php
namespace App\RadioFrontend;

use App\Utilities;
use Entity\Station;
use Entity\Settings;

class IceCast extends AdapterAbstract
{
    /* Process a nowplaying record. */
    protected function _getNowPlaying(&$np)
    {
        $fe_config = (array)$this->station->frontend_config;
        $radio_port = $fe_config['port'];

        $np_url = 'http://localhost:'.$radio_port.'/status-json.xsl';

        \App\Debug::log($np_url);

        $return_raw = $this->getUrl($np_url);

        if (!$return_raw)
            return false;

        $return = @json_decode($return_raw, true);

        \App\Debug::print_r($return);

        if (!$return || !isset($return['icestats']['source']))
            return false;

        $sources = $return['icestats']['source'];

        if (empty($sources))
            return false;

        if (key($sources) === 0)
            $mounts = $sources;
        else
            $mounts = array($sources);

        if (count($mounts) == 0)
            return false;

        // Sort in descending order of listeners.
        usort($mounts, function($a, $b) {
            $a_list = (int)$a['listeners'];
            $b_list = (int)$b['listeners'];

            if ($a_list == $b_list)
                return 0;
            else
                return ($a_list > $b_list) ? -1 : 1;
        });

        $temp_array = $mounts[0];

        if (isset($temp_array['artist']))
        {
            $np['current_song'] = array(
                'artist' => $temp_array['artist'],
                'title' => $temp_array['title'],
                'text' => $temp_array['artist'].' - '.$temp_array['title'],
            );
        }
        else
        {
            $np['current_song'] = $this->getSongFromString($temp_array['title'], ' - ');
        }

        $np['meta']['status'] = 'online';
        $np['meta']['bitrate'] = $temp_array['bitrate'];
        $np['meta']['format'] = $temp_array['server_type'];

        $np['listeners']['current'] = (int)$temp_array['listeners'];

        return true;
    }

    public function getStreamUrl()
    {
        $base_url = Settings::getSetting('base_url');
        
        $fe_config = (array)$this->station->frontend_config;
        $radio_port = $fe_config['port'];

        // Vagrant port-forwarding mode.
        if (APP_APPLICATION_ENV == 'development' && $radio_port == 8000)
            $radio_port = 8088;

        /* TODO: Abstract out mountpoint names */
        return 'http://'.$base_url.':'.$radio_port.'/radio.mp3?played='.time();
    }

    public function read()
    {
        $config = $this->_getConfig();

        $frontend_config = [
            'port'      => $config['listen-socket']['port'],
            'source_pw' => $config['authentication']['source-password'],
            'admin_pw'  => $config['authentication']['admin-password'],
        ];

        $this->station->frontend_config = $frontend_config;
        return true;
    }

    public function write()
    {
        $config = $this->_getDefaults();

        $frontend_config = (array)$this->station->frontend_config;

        $config['listen-socket']['port'] = $frontend_config['port'];
        $config['authentication']['source-password'] = $frontend_config['source_pw'];
        $config['authentication']['admin-password'] = $frontend_config['admin_pw'];

        $config_path = $this->station->getRadioConfigDir();
        $icecast_path = $config_path.'/icecast.xml';

        $writer = new \App\Xml\Writer;
        $icecast_config_str = $writer->toString($config, 'icecast');

        // Strip the first line (the XML charset)
        $icecast_config_str = substr( $icecast_config_str, strpos($icecast_config_str, "\n")+1 );

        file_put_contents($icecast_path, $icecast_config_str);
    }

    /*
     * Process Management
     */

    public function isRunning()
    {
        $config_path = $this->station->getRadioConfigDir();
        $icecast_pid_file = $config_path.'/icecast.pid';

        if (file_exists($icecast_pid_file))
        {
            $icecast_pid = file_get_contents($icecast_pid_file);
            $pid_result = exec('ps --pid '.$icecast_pid.' &>/dev/null');

            return ($pid_result == 0);
        }

        return false;
    }

    public function stop()
    {
        $config_path = $this->station->getRadioConfigDir();
        $icecast_pid_file = $config_path.'/icecast.pid';

        if (file_exists($icecast_pid_file))
        {
            $icecast_pid = file_get_contents($icecast_pid_file);
            $kill_result = exec('kill -9 '.$icecast_pid);

            @unlink($icecast_pid_file);
            return $kill_result;
        }

        return null;
    }

    public function start()
    {
        $config_path = $this->station->getRadioConfigDir();
        $icecast_config = $config_path.'/icecast.xml';

        exec('icecast2 -b -c '.$icecast_config, $output);
        return implode("\n", $output);
    }

    public function restart()
    {
        $return = array();
        $return[] = $this->stop();
        $return[] = $this->start();

        return implode("\n", $return);
    }

    /*
     * Configuration
     */

    protected function _getConfig()
    {
        $config_path = $this->station->getRadioConfigDir();
        $icecast_path = $config_path.'/icecast.xml';

        $defaults = $this->_getDefaults();

        if (file_exists($icecast_path))
        {
            $reader = new \App\Xml\Reader;
            $data = $reader->fromFile($icecast_path);

            return Utilities::array_merge_recursive_distinct($defaults, $data);
        }

        return $defaults;
    }

    protected function _getDefaults()
    {
        $config_dir = $this->station->getRadioConfigDir();

        return [
            'location' => 'Earth',
            'admin' => 'icemaster@localhost',
            'limits' => [
                'clients' => 100,
                'sources' => 2,
                'threadpool' => 5,
                'queue-size' => 524288,
                'client-timeout' => 30,
                'header-timeout' => 15,
                'source-timeout' => 10,
                'burst-on-connect' => 1,
                'burst-size' => 65535,
            ],
            'authentication' => [
                'source-password' => Utilities::generatePassword(),
                'relay-password' => Utilities::generatePassword(),
                'admin-user' => 'admin',
                'admin-password' => Utilities::generatePassword(),
            ],
            'hostname' => 'localhost',
            'listen-socket' => [
                'port' => 8000,
            ],
            'mount' => [
                '@type'     => 'normal',
                'mount-name' => '/radio.mp3',
            ],
            'fileserve' => 1,
            'paths' => [
                'basedir' => '/usr/share/icecast2',
                'logdir' => $config_dir,
                'webroot' => '/usr/share/icecast2/web',
                'adminroot' => '/usr/share/icecast2/admin',
                'pidfile' => $config_dir.'/icecast.pid',
                'alias' => [
                    '@source' => '/',
                    '@destination' => '/status.xsl',
                ],
            ],
            'logging' => [
                'accesslog' => 'icecast_access.log',
                'errorlog' => 'icecast_error.log',
                'loglevel' => 3,
                'logsize' => 10000,
            ],
            'security' => [
                'chroot' => 0,
            ],
        ];
    }
}