<?
session_start();
ini_set('display_errors', true);
ini_set('html_errors', false);
ini_set('error_reporting', E_ALL);

define('AntiBK', true);

include_once("engline/config.php");
include_once("engline/dbsimple/Generic.php");
include_once("engline/data/data.php");
include_once("engline/functions/functions.php");

$adb = DbSimple_Generic::connect($database['adb']);
$adb->query("SET NAMES ? ",$database['db_encoding']);
$adb->setErrorHandler("databaseErrorHandler");

$register = $adb->selectCell("SELECT `registration` FROM `server_info`;");
$register_error = 'Регистрация закрыта!<br><a href="index.php" class="us2">Вернуться на главную</a><br>';
$do = getVar('do');
switch ($do)
{
  case 'checkstep1':
    if (!$register)
      returnAjax('error', $register_error);
    
    unset($_SESSION['reg_login']);
    $login = getVar('login');
    $login_check = $adb->selectCell("SELECT `guid` FROM `characters` WHERE `login` = ?s", $login);
    $match = false;
    $file = file("regfail.dat");
    $mat = explode(',', $file[0]);
    $zapret = explode(',', $file[1]);
    foreach ($zapret as $value)
    {
      $check = '';
      for ($i = 0; $i < utf8_strlen($value); $i++)
      {
        $s = utf8_substr($value, $i, 1);
        $check .= "[".lowercase($s)."|".uppercase($s)."]+";
      }
      if (preg_match("/$check/ui", $login))
        returnAjax('error', "Все вариации логина $value запрещены.");
    }
    foreach ($mat as $value)
    {
      $check = '';
      for ($i = 0; $i < utf8_strlen($value); $i++)
      {
        $s = utf8_substr($value, $i, 1);
        $check .= "[".lowercase($s)."|".uppercase($s)."]+";
      }
      if (preg_match("/$check/ui", $login))
        returnAjax('error', 'Выберите, пожалуйста, другой логин.');
    }
    for ($i = 0; $i < utf8_strlen($login); $i++)
    {
      $s = utf8_substr($login, $i, 1);
      
      if ($i >= 2 && lowercase($s) == lowercase(utf8_substr($login, ($i-1), 1)) && lowercase($s) == lowercase(utf8_substr($login, ($i-2), 1)))
        returnAjax('error', 'Запрещено использование трех и более одинаковых символов подряд.');
      
      if (($i == 0 || $i == (utf8_strlen($login) - 1)) && ($s == '_' || $s == '-'))
        returnAjax('error', 'Логин не может начинаться или заканчиваться пробелом, подчеркиванием или тире.');
      
      if ($i >= 1 && in_array($s, mb_str_split(UPCASE)) && in_array(utf8_substr($login, ($i-1), 1), mb_str_split(LOCASE)))
        returnAjax('error', 'Логин не может содержать заглавную букву после обычной.');
    }
    
    if (utf8_strlen($login) < 2 || utf8_strlen($login) > 15 || preg_match("/[^a-zA-Zа-яА-Я\-_]/ui", $login))
      returnAjax('error', 'Логин не может быть короче 2-х символов и длинее 15-ти символов, и должен состоять только из букв русского и английского алфавита, а также из тире и символа_.');
    else if (preg_match("/[\-]+[\-]/ui", $login) || preg_match("/[\_]+[\_]/ui", $login))
      returnAjax('error', 'Запрещено использовать два разделительных символа подряд.');
    else if (preg_match("/[a-zA-Z]/ui", $login) && preg_match("/[а-яА-Я]/ui", $login))
      returnAjax('error', 'В логине разрешено использовать только буквы одного алфавита русского или английского. Нельзя смешивать.');
    else if ($login_check)
      returnAjax('error', "Логин $login уже занят, выберите другой.");

    $_SESSION['reg_login'] = $login;
    returnAjax('complete');
  break;
  case 'checkstep2':
    if (!$register)
      returnAjax('error', $register_error);
    
    unset($_SESSION['reg_password']);
    $password = getVar('password');
    $password_confirm = getVar('password_confirm');
    
    if (!checks('reg_login'))
      returnAjax('error', 'Пройдите предыдущий шаг!');
    else if (utf8_strlen($password) < 6 || utf8_strlen($password) > 30)
      returnAjax('error', 'Длина пароля не может быть меньше 6 символов или более 30 символов.');
    else if ($password_confirm == '')
      returnAjax('error', 'В анкете пароль нужно ввести дважды, для проверки.');
    else if ($password != $password_confirm)
      returnAjax('error', 'Во второй раз вы ввели пароль неверно, будьте внимательнее...');
    else if (preg_match("/[^a-zA-Zа-яА-Я0-9_]/ui", $password))
      returnAjax('error', 'Пароль должен состоять только из букв русского и английского алфавита, а также из цифр.');
    else if ((preg_match("/[a-zA-Z]/ui", $password) && !preg_match("/[а-яА-Я0-9_]/ui", $password)) || (preg_match("/[а-яА-Я]/ui", $password) && !preg_match("/[a-zA-Z0-9_]/ui", $password)) || (preg_match("/[0-9_]/ui", $password) && !preg_match("/[a-zA-Zа-яА-Я]/ui", $password)))
      returnAjax('error', 'Пароль не должен содержать только буквы одной раскладки и одного регистра.');
    else if (preg_match("/$_SESSION[reg_login]/ui", $password))
      returnAjax('error', 'Пароль не должен содержать части логина.');
    
    $_SESSION['reg_password'] = $password;
    returnAjax('complete');
  break;
  case 'checkstep3':
    if (!$register)
      returnAjax('error', $register_error);
    
    unset($_SESSION['reg_email'], $_SESSION['reg_secretquestion'], $_SESSION['reg_secretanswer']);
    $email = getVar('email');
    $secretquestion = getVar('secretquestion');
    $secretanswer = getVar('secretanswer');
    
    if (!checks('reg_login', 'reg_password'))
      returnAjax('error', 'Пройдите предыдущий шаг!');
    else if (utf8_strlen($email) < 6 || utf8_strlen($email) > 50)
      returnAjax('error', 'Email не может быть короче 6-х символов и длинее 50-ти символов.');
    else if (!preg_match("/^\w+[-_\.]*\w+@\w+-?\w+\.[a-z]{2,4}$/ui", $email))
      returnAjax('error', "Вы указали явно ошибочный email($email).");
    
    $_SESSION['reg_email'] = $email;
    $_SESSION['reg_secretquestion'] = $secretquestion;
    $_SESSION['reg_secretanswer'] = $secretanswer;
    returnAjax('complete');
  break;
  case 'checkstep4':
    if (!$register)
      returnAjax('error', $register_error);
    
    unset($_SESSION['reg_name'], $_SESSION['reg_birth_day'], $_SESSION['reg_birth_month'], $_SESSION['reg_birth_year'], $_SESSION['reg_sex'], $_SESSION['reg_city'], $_SESSION['reg_icq'], $_SESSION['reg_hide_icq'], $_SESSION['reg_motto'], $_SESSION['reg_color']);
    $name = getVar('name');
    $birth_day = getVar('birth_day');
    $birth_month = getVar('birth_month');
    $birth_year = getVar('birth_year');
    $sex = getVar('sex');
    $city_n = getVar('city_n');
    $city = getVar('city');
    $icq = getVar('icq');
    $hide_icq = getVar('hide_icq');
    $motto = getVar('motto');
    $color = getVar('color');
    
    if (!checks('reg_login', 'reg_password', 'reg_email', 'reg_secretquestion', 'reg_secretanswer'))
      returnAjax('error', 'Пройдите предыдущий шаг!');
    else if ($name == '' || preg_match("/[^a-zA-Zа-яА-Я0-9]/ui", $name))
      returnAjax('error', 'Вы не заполнили или не правильно заполнили обязательное поле "Ваше имя".');
    else if ($birth_day == '')
      returnAjax('error', 'Вы не указали день своего рождения.');
    else if ($birth_month == '')
      returnAjax('error', 'Вы не указали месяц своего рождения.');
    else if ($birth_year == '')
      returnAjax('error', 'Вы не указали год своего рождения.');
    else if (preg_match("/[^a-zA-Zа-яА-Я0-9]/ui", $city))
      returnAjax('error', 'Город должен состоять только из букв русского и английского алфавита, а также из цифр.');
    else if (preg_match("/[^0-9]/ui", $icq))
      returnAjax('error', 'ICQ должна состоять только из цифр.');
    
    $_SESSION['reg_name'] = $name;
    $_SESSION['reg_birth_day'] = $birth_day;
    $_SESSION['reg_birth_month'] = $birth_month;
    $_SESSION['reg_birth_year'] = $birth_year;
    $_SESSION['reg_sex'] = $sex;
    $_SESSION['reg_city'] = ($city_n) ?$city_n :$city;
    $_SESSION['reg_icq'] = $icq;
    $_SESSION['reg_hide_icq'] = ($hide_icq == 'true') ?1 :0;
    $_SESSION['reg_motto'] = $motto;
    $_SESSION['reg_color'] = $color;
    returnAjax('complete');
  break;
  case 'checkstep5':
    if (!$register)
      returnAjax('error', $register_error);
    
    $rules = getVar('rules');
    
    if (!checks('reg_login', 'reg_password', 'reg_email', 'reg_secretquestion', 'reg_secretanswer', 'reg_name', 'reg_birth_day', 'reg_birth_month', 'reg_birth_year', 'reg_sex', 'reg_city', 'reg_icq', 'reg_hide_icq', 'reg_motto', 'reg_color'))
      returnAjax('error', 'Пройдите предыдущий шаг!');
    else if ($rules == 'false')
      returnAjax('error', 'Извините, без принятия правил нашего клуба, вы не можете зарегистрировать своего персонажа.');
    
    $login = $_SESSION['reg_login'];
    $password = $_SESSION['reg_password'];
    $email = $_SESSION['reg_email'];
    $secretquestion = $_SESSION['reg_secretquestion'];
    $secretanswer = $_SESSION['reg_secretanswer'];
    $name = $_SESSION['reg_name'];
    $birthday = $_SESSION['reg_birth_day'].".".$_SESSION['reg_birth_month'].".".$_SESSION['reg_birth_year'];
    $sex = $_SESSION['reg_sex'];
    $town = $_SESSION['reg_city'];
    $icq = $_SESSION['reg_icq'];
    $hide_icq = $_SESSION['reg_hide_icq'];
    $motto = $_SESSION['reg_motto'];
    $color = $_SESSION['reg_color'];
    $city = (rand(1,2) == 1) ?'drm' :'low';
    $shape = ($sex == "male") ?"m/0.gif" :"f/0.gif";
    unset($_SESSION['reg_login'], $_SESSION['reg_password'], $_SESSION['reg_email'], $_SESSION['reg_secretquestion'], $_SESSION['reg_secretanswer'], $_SESSION['reg_name'], $_SESSION['reg_birth_day'], $_SESSION['reg_birth_month'], $_SESSION['reg_birth_year'], $_SESSION['reg_sex'], $_SESSION['reg_city'], $_SESSION['reg_icq'], $_SESSION['reg_hide_icq'], $_SESSION['reg_motto'], $_SESSION['reg_color']);
    
    if ($adb->selectCell("SELECT COUNT(*) FROM `characters` WHERE `login` = ?s", $login) != 0)
      returnAjax('error', 'Персонаж уже создан.');
    
    $guid = ($adb->selectCell("SELECT MAX(`guid`) FROM `characters`;")) + 1;
    $reg_password = SHA1($guid.':'.$password);
    $char = Char::initialization($guid, $adb);
    // Основная база
    $adb->query("INSERT INTO `characters` (`guid`, `login`, `login_sec`, `password`, `mail`, `sex`, `city`, `shape`, `reg_ip`, `last_time`) 
                 VALUES (?d, ?s, ?s, ?s, ?s, ?s, ?s, ?s, ?s, ?d);", $guid ,$login ,$login ,$reg_password ,$email ,$sex ,$city ,$shape ,$_SERVER['REMOTE_ADDR'] ,time());
    // Дополнительная информация
    $adb->query("INSERT INTO `character_info` (`guid`, `name`, `icq`, `secretquestion`, `secretanswer`, `hide_icq`, `town`, `birthday`, `color`, `motto`, `state`, `date`) 
                 VALUES (?d, ?s, ?s, ?s, ?s, ?d, ?s, ?s, ?s, ?s, ?s, ?d);", $guid ,$name ,$icq ,$secretquestion ,$secretanswer ,$hide_icq ,$town ,$birthday ,$color ,$motto ,$city ,time());
    // Характеристики
    $adb->query("INSERT INTO `character_stats` (`guid`) 
                 VALUES (?d);", $guid);
    $stats = $config['start']['stats'];
    $items = $config['start']['items'];
    unset($config['start']['stats'], $config['start']['items']);
    $char->setChar('char_stats', $config['start']);
    $char->changeStats($stats);
    // Создание инвентаря
    $adb->query("INSERT INTO `character_equip` (`guid`) 
                 VALUES (?d);", $guid);
    // Создание баров
    $adb->query("INSERT INTO `character_bars` (`guid`) 
                 VALUES (?d);", $guid);
    // Эффекты
    $char->workEffect(1);
    // Предметы
    $i = 1;
    foreach ($items as $item)
    {
      $char->equip->addItem($item, 'get');
      $char->equip->equipItem($i);
      $i++;
    }
    $char->history->Auth(2, $city);
    
    if (checks('guid'))
      deleteSession();
    
    $adb->query("DELETE FROM `online` WHERE `guid` = ?d", $guid);
    $adb->query("INSERT INTO `online` (`guid`, `login_display`, `ip`, `city`, `room`, `last_time`) 
                 VALUES (?d, ?s, ?s, ?s, ?s, ?d);", $guid ,$login ,$_SERVER['REMOTE_ADDR'] ,$city ,'novice' ,time());
    $char->setChar('char_db', array('last_go' => time()));
    $_SESSION['guid'] = $guid;
    $_SESSION['zayavka_c_m'] = 1;
    $_SESSION['zayavka_c_o'] = 1;
    $_SESSION['battle_ref']  = 0;
    $char->history->Auth(1, $city);
    returnAjax('complete');
  break;
}
?>