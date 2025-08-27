<?php

namespace LaraDumps\LaraDumpsCore\Actions;

use Spatie\Backtrace\Frame;

class IdeHandle
{
        /** @var  */
    public $frame;

    public function __construct(
        $frame= null
    ) {
        $this->frame = $frame;
    }

    public function make(): array
    {
        $workDir = Config::get('app.workdir');

        $projectPath = Config::get('app.project_path');
        if(empty($projectPath)){
            $projectPath = '';
        }

        /** @var null;
         *
         *

        /** @var null $wslConfig */
        $wslConfig = Config::get('app.wsl_config');

        if (empty($this->frame)) {
            return [
                'path'         => 'empty',
                'class_name'   => 'empty',
                'real_path'    => '',
                'project_path' => '',
                'line'         => '',
                'separator'    => DIRECTORY_SEPARATOR,
                'wsl_config'   => $wslConfig,
            ];
        }

        $realPath = $this->frame->file;
        $line     = strval($this->frame->lineNumber);

        $realPath = str_replace(appBasePath() . DIRECTORY_SEPARATOR, '', strval($realPath));

        $className = explode(DIRECTORY_SEPARATOR, $realPath);
        $className = end($className);

        return [
            'workdir'      => $workDir,
            'project_path' => $projectPath ?? '',
            'real_path'    => $realPath,
            'class_name'   => $className,
            'line'         => $line,
            'separator'    => DIRECTORY_SEPARATOR,
            'wsl_config'   => $wslConfig,
        ];
    }
}
