<?php
    require 'vendor/autoload.php';
    require 'src/RailWayBot.php';

    use railway\RailWayBot\RailWayBot;
    use Hoa\Console\Readline\Readline;
    use Povils\Figlet\Figlet;
    use Hoa\Console\Parser;
    use Hoa\Console\GetOption;
    use Hoa\Console\Cursor;

    $parser = new Parser();
    unset($argv[0]);
    $command = implode(' ', $argv);
    
    $parser = new Parser();
    $parser->parse($command);
    
    //options definition
    //['longname', TYPE, 'shortname']
    $options = new GetOption(
        [
            ['setting', GetOption::OPTIONAL_ARGUMENT, 's'],
            ['dates', GetOption::NO_ARGUMENT, 'd'],
            ['station_code', GetOption::NO_ARGUMENT, 'c'],
        ],
        $parser
    );
    //definition of default values
    $setting = 'Hello';
    $color = 'red';

    $names = $parser->getInputs();

    $color = 'red';
    Cursor::colorize('fg('.$color.')');

    //The following while with the switch will assign the values to the variables.
    while (false !== $shortName = $options->getOption($value)) {
        switch ($shortName) {
            case 's':
                $setting = $value;
                break;
            case 'd':
                $setting = 'dates';
                break;
            case 'c':
                $setting = 'station_code';
                break;
            default:
                $setting = 'unknown';
        }
    }

    if ($setting === 'Hello') {
        // Default font is "big"
        $figlet = new Figlet();
        
        //Outputs "Figlet" text using "small" red font in blue background.
        $figlet
            ->setFont('small')
            ->setFontColor('white')
            ->setBackgroundColor('black')
            ->write('RailWayBot');

        echo 'The version is 1.0.0'.PHP_EOL;
        echo 'Help you booking the railway ticket easily!'.PHP_EOL;
        exit(0);
    } else if ($setting === 'dates') {
        $bot = new RailWayBot(null);
        $vaildDates = $bot->getAvailableDate();
        if ($vaildDates === 'failed to get dates lists') {
            echo $vaildDates.PHP_EOL;
            exit(1);
        } else {
            $color = 'yellow';
            Cursor::colorize('fg('.$color.')');
            echo 'available dates 可以選擇的乘車日期:'.PHP_EOL;
            $color = 'green';
            Cursor::colorize('fg('.$color.')');
            foreach($vaildDates as $value) {
                echo $value.PHP_EOL;
            }

            exit(0);
        }
    } else if ($setting === 'station_code') {
        $color = 'white';
        Cursor::colorize('fg('.$color.')');
        $bot = new RailWayBot(null);
        $response = $bot->getStationCode();
        $tables = new Console_Table();
        $tables->setHeaders(
            ['站名稱', 'code 站代碼']
        );

        foreach($response as $key => $value) {
            $tables->addRow([$key, $value]);
        }
        echo 'The station codes are as followed 站台列表如下:'.PHP_EOL;
        echo $tables->getTable();
        echo 'It can also refer this link 也可參考此網頁連結:'.PHP_EOL;
        echo 'http://railway.hinet.net/station_code.htm'.PHP_EOL;
        exit(0);
    } else if (file_exists($setting)) {
        $color = 'white';
        Cursor::colorize('fg('.$color.')');
        echo 'Start booking the ticket...'.PHP_EOL;
    } else {
        echo 'Unknown options...'.PHP_EOL;
        exit(1);
    }

    $fget = false;
    if (PHP_OS === 'WINNT') {
        $fget = true;
    }

    $bot = new RailWayBot($setting);
    $response = $bot->checkCtnnumber();

    if ($response === false) {
        echo 'Some thing error happen.Please check out the error.log'.PHP_EOL;
        exit(1);
    } if($response === 'setting file not found') {
        $color = 'red';
        Cursor::colorize('fg('.$color.')');
        echo $response.PHP_EOL;
        exit(1);
    }

    $readline = new Readline();
    $captchaCode = null;
    while (true) {
        echo 'please fill the captcha code: ';
        if($fget) {
            $line = fgets(STDIN);
        } else {
            $line = $readline->readLine('> ');
        }

        $captchaCode = trim($line);
        break;
    }

    $color = 'yellow';
    Cursor::colorize('fg('.$color.')');
    echo 'Waiting...'.PHP_EOL;
    $response = $bot->bookTicket($captchaCode);

    if($response === false) {
        $color = 'red';
        Cursor::colorize('fg('.$color.')');
        echo 'Some thing error happen.Please check out the error.log'.PHP_EOL;
        exit(1);
    } else {
        $color = 'green';
        Cursor::colorize('fg('.$color.')');
        $result = $bot->parseResult($response);
        foreach($result as $value) {
            echo $value.PHP_EOL;
        }
    }

    $color = 'white';
    Cursor::colorize('fg('.$color.')');
