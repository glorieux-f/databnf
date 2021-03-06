<?php
/**
 * Classe un peu bricolée pour charger une base SQLite avec des données
 * DataBNF http://data.bnf.fr/semanticweb
 * TODO, BUG, le commit n’a pas l’air fini quand on passe à un autre lot de fichiers
 * Pas encore complètement automatisé
 */
mb_internal_encoding ("UTF-8");
Databnf::connect("databnf.sqlite");


Databnf::download(); // téléchargement avant toute chose
Databnf::subject();
Databnf::orgs(); // pas encore fait
Databnf::works(); // œuvres avant les documents
Databnf::persons(); // personnes avant les documents
Databnf::documents();
Databnf::contributions(); // lien entre personnes et documents
Databnf::studies();

class Databnf
{
  /** Table de prénoms (pour reconnaître le sexe), chargée depuis given.php */
  static $given;
  /** Table de caractères pour mise à l’ASCII, chargée depuis frtr.php */
  static $frtr;
  /** lien à la base de donnée */
  static $pdo;
  /** des requêtes préparées */
  static $q;
  /** des compteurs */
  static $stats;
  /* des tables de rôles */
  // TODO contribution role
  public static $roles = array(
    "writes" => array(70=>"", 71=>"", 72=>"", 73=>"", 980=>"", 990=>""),
    "edition" => "(360, 540, 550)",
    "traduction" => "(680)",
    "spectacle" => "(1010, 1011, 1013, 1017, 1018, 1020, 1050, 1060, 1080, 1090)",
    "musique" => "(220, 221, 222, 223, 510, 1030, 1031, 1033, 1039, 1040, 1100, 1101, 1103, 1108, 1120, 1129, 1130, 1139, 1140, 1149, 1150, 1159, 1160, 1169, 1170, 1179, 1180, 1189, 1190, 1197, 1199, 1200, 1210, 1217, 1218, 1219, 1220, 1229, 1230, 1239, 1240, 1249, 1250, 1257, 1258, 1260, 1268, 1270, 1277, 1278, 1280, 1287, 1288, 1289, 1290, 1299, 1300, 1309, 1310, 1317, 1318, 1320, 1330, 1337, 1340, 1350, 1357, 1358, 1360, 1367, 1368, 1370, 1377, 1378, 1380, 1387, 1388, 1389, 1390, 1400, 1407, 1410, 1418, 1420, 1427, 1428, 1430, 1437, 1438, 1440, 1450, 1459, 1460, 1470, 1477, 1478, 1480, 1490, 1500, 1510, 1520, 1527, 1530, 1537, 1540, 1550, 1557, 1558, 1560, 1567, 1569, 1570, 1580, 1587, 1590, 1597, 1598, 1599, 1600, 1607, 1610, 1620, 1630, 1637, 1638, 1640, 1649, 1650, 1651, 1653, 1657, 1658, 1659, 1660, 1667, 1668, 1670, 1680, 1688, 1690, 1700, 1707, 1710, 1717, 1718, 1720, 1728, 1730, 1738, 1740, 1747, 1748, 1750, 1760, 1767, 1770, 1777, 1780, 1787, 1790, 1797, 1798, 1800, 1807, 1810, 1817, 1818, 1820, 1827, 1828, 1830, 1837, 1840, 1850, 1860, 1870, 1878, 1880, 1888, 1890, 1898, 1900, 1910, 1920, 1930, 1937, 1938, 1940, 1947, 1948)",
    "illustration" => "(440, 520, 521, 522, 523, 524, 530, 531, 532, 533, 534)",
);

  /**
   * Télécharger les données
   */
  static function download()
  {
    $files = array(
      "ftp://databnf:databnf@pef.bnf.fr/DATA/databnf_contributions_n3.tar.gz",
      "ftp://databnf:databnf@pef.bnf.fr/DATA/databnf_editions_n3.tar.gz",
      "ftp://databnf:databnf@pef.bnf.fr/DATA/databnf_person_authors_n3.tar.gz",
      "ftp://databnf:databnf@pef.bnf.fr/DATA/databnf_org_authors_n3.tar.gz",
      "ftp://databnf:databnf@pef.bnf.fr/DATA/databnf_works_n3.tar.gz",
      "ftp://databnf:databnf@pef.bnf.fr/DATA/databnf_study_n3.tar.gz",
      "ftp://databnf:databnf@pef.bnf.fr/DATA/databnf_periodics_n3.tar.gz",
  );
    foreach($files as $src) {
      $name = basename($src);
      if ($pos=strpos($name, '?')) $name = substr($name, 0, $pos);
      $arc = dirname(__FILE__).'/'.$name;
      echo $name;
      if (!file_exists($arc)) {
        echo " …téléchargement… ";
        copy($src, $arc);
      }
      preg_match('@databnf_([^_]+)_@', $name, $matches);
      $dir = dirname(__FILE__).'/'.$matches[1].'/';
      if (!file_exists($dir)) {
        echo " …décompression… ";
        mkdir($dir);
        // pas compatible windows
        $cmd = 'tar -zxf '.$arc." -C ".$dir;
        echo "\n".$cmd."\n";
        passthru($cmd);
      }
      echo " OK\n";
    }
    // tar -zxvf
  }

  /**
   * Connexion à la base de données
   */
  static function connect($sqlfile, $create=false)
  {
    $dsn = "sqlite:".$sqlfile;
    if($create && file_exists($sqlfile)) unlink($sqlfile);
    // create database
    if (!file_exists($sqlfile)) { // if base do no exists, create it
      echo "Base, création ".$sqlfile."\n";
      if (!file_exists($dir = dirname($sqlfile))) {
        mkdir($dir, 0775, true);
        @chmod($dir, 0775);  // let @, if www-data is not owner but allowed to write
      }
      self::$pdo = new PDO($dsn);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      @chmod($sqlfile, 0775);
      self::$pdo->exec(file_get_contents(dirname(__FILE__)."/databnf.sql"));
      return;
    }
    else {
      // echo getcwd() . '  ' . $dsn . "\n";
      // absolute path needed ?
      self::$pdo = new PDO($dsn);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
  }

  /**
   * Scanner les organisation auteur
   */
  public static function orgs()
  {
    Databnf::$stats=array("org"=>0);
    $glob = dirname(__FILE__)."/org/*_foaf_*.n3";
    echo $glob."\n";
    foreach(glob($glob) as $filepath) {
      // on prépare les requêtes
      /*
      self::$pdo->beginTransaction();
      self::$q['org'] = self::$pdo->prepare("INSERT INTO
        org (ark, id)
        VALUES (?, ?)");
      */
      self::org($filepath);
      // self::$pdo->commit();
    }
  }

  /**
   * rdagroup2elements:dateOfEstablishment <http://data.bnf.fr/date/1248/> ;
   * rdagroup2elements:dateOfTermination <http://data.bnf.fr/date/1701-1800/> ;
   * rdagroup2elements:corporateHistory "Abbaye de Bénédictins. - Sous le vocable de la Sainte-Trinité, d'après Auguste Lerosey. - Cette abbaye subsista nominalement jusqu'à la Révolution, mais la vie monastique y était éteinte depuis 1774 ou environ"@fr,
   * "Depuis 1832, cette ancienne abbaye abrite la maison-mère des Sœurs de Sainte-Marie-Madeleine-Postel"@fr ;
   * rdagroup2elements:corporateHistory "Créé à l'origine comme \"Committee of the Sword of the Spirit\" devient une organisation indépendante : \" Africa Centre\" en février 1961\""@fr ;
   */
  public static function org($file)
  {
    $filename = basename($file);
    fwrite(STDERR, $filename."\n");
    $filestream = fopen($file, "r");
    $key = '';
    $value = '';
    $record = array();
    while (($line = fgets($filestream)) !== false) {
      $line = trim($line);
      // enregistrer la ligne
      if (isset($record['n3'])) $record['n3'].="\n".$line;

      // fin d’un enregistrement, enregistrer
      if (preg_match('@foaf:page <http://data.bnf.fr/[^>]*> \.@', $line, $matches)) {
        $record[ $key ] = $value; // denière propriété en suspens
        // RECORD
        // unset($record['n3']);
        if (isset($record['dateOfEstablishment'])) {
          preg_match('@date/([0-9]+)@', $record['dateOfEstablishment'], $match_date);
          if (@$match_date[1]) $record['start'] = $match_date[1];
        }
        if (isset($record['dateOfTermination'])) {
          // [dateOfTermination] => <http://data.bnf.fr/date/1701-1800/> ? prendre ou pas
          preg_match('@date/([0-9]+)@', $record['dateOfTermination'], $match_date);
          if (@$match_date[1]) $record['end'] = $match_date[1];
        }
        $record = null;
      }
      // debut d’un enregistrement qui nous intéresse
      else if (preg_match('@<(http://)?data.bnf.fr/ark:/12148/([^#>]+)(#[^>]+)?> a [^ ]+ ;@', $line, $match_ark)) {
        $record = array();
        // enregistrer le texte rdf
        $record['n3'] = $line;
        $record['ark'] = $match_ark[2];
        $key = "";
        $value = "";
      }
      // pas encore d’enregistrement
      else if (!$record);
      /*
      = <http://data.bnf.fr/ark:/12148/cb11864637w#about>,
          <http://viaf.org/viaf/155482113>,
          <http://www.idref.fr/153580267/id> ;
      */
      else if ($line[0] == '=') {
        if ($key) $record[ $key ] = $value;
        $key = $value = null;
      }
      // début d’une propriété
      else if (preg_match('@[a-z\-]+:([a-zA-Z\-]+) (.+)@', $line, $match_kv)) {
        // enregistrer la propriété précédente
        if ($key) $record[ $key ] = $value;
        $key = $match_kv[1];
        $value = self::value($match_kv[2]);
      }
      // valeur en cours
      else {
        $value .= "\n".self::value($line);
      }
    }
  }
  /**
   * Nettoyer un valeur attrapée dans un fichier n3
   */
  static public function value($string)
  {
    $value = stripslashes(
      preg_replace(
        '/^"|"(@fr)?[, ;.]*$/', '',
        trim($string)
    )
  );
    if (strpos($value, ".") !== false && preg_match('/\pL$/u', $value)) $value .= ".";
    return $value;
  }
  /**
   *
   */
  static public function stats($glob)
  {
    self::$stats = array();
    $microtime = microtime(true);
    Databnf::scanglob($glob, Array("Databnf", "fstats"));
    echo (microtime(true) - $microtime) . " s.\n";
    foreach(self::$stats as $key=>$value) {
      echo $key . "\t" . $value . "\n";
    }
  }

  /**
   *
   */
  static public function fstats($filename)
  {
    fwrite(STDERR, $filename . "\n");
    $res = fopen($filename, 'r');
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      // try catch 7.58 mais message

      if (!$line);
      else if (strpos($line, 'a frbr:Work ;') !== false) {
        if(!isset(self::$stats['record'])) self::$stats['record'] = 1;
        else self::$stats['record']++;
      }
      else if ('<' == $line[0] || '@' == $line[0]);
      else if (preg_match('@^[^ :]+:[^ :]+@', $line, $matches)) {
        if (!isset(self::$stats[$matches[0]])) self::$stats[$matches[0]] = 1;
        else self::$stats[$matches[0]]++;
      }
    }
  }

  /**
   * Charger tous les documents dans la base
   */
  static public function documents()
  {
    // les documents, avec leurs liens à des titres (works)
    self::manif();
    // d’autres informations sur les documents (langue)
    self::expr();
  }

  /**
   * Boucler sur les sujets
   */
  static public function subject($function="Databnf::subjectSql")
  {
    $glob = dirname(__FILE__).'/editions/databnf_editions__manif_*.n3';
    echo $glob."\n";
    foreach(glob($glob) as $filepath) {
      // on prépare les requêtes
      self::$pdo->beginTransaction();
      self::$q['subject'] = self::$pdo->prepare(
        "INSERT INTO subject (document, rameau, url) VALUES (?, ?, ?)"
    );
      self::fdo($filepath, $function);
      self::$pdo->commit();
    }
  }

  static public function subjectSql($record)
  {
    if(count($record['subject']) < 1) return;
    foreach ($record['subject'] as $key => $value) {
      try {
        self::$q["subject"]->execute(array(
          self::ark2id($record['ark']),
          self::ark2id($key),
          $key
      ));
      }
      catch(Exception $e) {
        echo "  Doublon ? ".$record['ark']." ".$key."\n";
      }
    }
  }

  /**
   * Charger les notices de documents
   *
   */
  static public function manif($function="Databnf::manifSql")
  {
    $glob = dirname(__FILE__).'/editions/databnf_editions__manif_*.n3';
    echo $glob."\n";
    foreach(glob($glob) as $filepath) {
      // on prépare les requêtes
      self::$pdo->beginTransaction();
      self::$q['doc'] = self::$pdo->prepare("INSERT INTO
        document (ark, type, title, dateline, date, imprint, place, publisher, description, pages, size, gallica, id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      self::$q['version'] = self::$pdo->prepare("INSERT INTO version (document, work) VALUES (?, ?)");
      self::$q['title'] = self::$pdo->prepare("INSERT INTO title (docid, text) VALUES (?, ?)");
      self::fdo($filepath, $function);
      self::$pdo->commit();
    }
    echo "Normalisations : Paris…";
    self::$pdo->exec("UPDATE document SET paris=1 WHERE place IN ('Paris', 'Parisiis', 'A Paris', 'Lutetiae Parisiorum', 'Paris ?', 'À Paris', 'Suivant la copie imprimée à Paris', 'Se vend à Paris') ");
    echo " pages > 2500…";
    self::$pdo->exec("UPDATE document SET pages = NULL WHERE pages > 2500");
    echo " index titres…";
    self::$pdo->exec("INSERT INTO title(title) VALUES('optimize');");
    echo " a une version Gallica…";
    self::$pdo->exec("UPDATE document SET hasgall = 1 WHERE gallica IS NOT NULL");
    echo " FINI.\n";
  }



  /**
   * Charger une notice de document
   * <http://data.bnf.fr/ark:/12148/cb40671347p> a frbr:Manifestation ;
   *   bnf-onto:FRBNF 40671347 ;
   *   bnf-onto:firstYear 1700 ;
   *   dcterms:date "1700/1799" ;
   *   dcterms:description "1 carte : 21,5 x 16,5 cm" ;
   *   dcterms:publisher "[S.l.] : [s.n.] , [17..]" ;
   *
   *
   * <http://data.bnf.fr/ark:/12148/cb39605922n> a frbr:Manifestation ;
   * bnf-onto:FRBNF 39605922 ;
   * bnf-onto:firstYear 1993 ;
   * dcterms:date "cop. 1993" ;
   * dcterms:description "1 partition (3 p.) : 30 cm" ;
   * dcterms:publisher "Lyon : A coeur joie , cop. 1993" ;
   * dcterms:subject <http://data.bnf.fr/ark:/12148/cb119329384>,
   *    <http://data.bnf.fr/ark:/12148/cb11975995h>,
   *    <http://data.bnf.fr/ark:/12148/cb14623720f> ;
   * dcterms:title "La blanche neige : [choeur] à 3 voix égales a cappella ou avec accompagnement de piano ad libitum" ;
   * rdagroup1elements:dateOfPublicationManifestation <http://data.bnf.fr/date/1993/> ;
   * rdagroup1elements:note "Note : Titre général : \"Six choeurs de Guillaume Apollinaire, extraits de Alcools\" ; 1. - Durée : 2'30" ;
   * rdagroup1elements:placeOfPublication "Lyon" ;
   * rdagroup1elements:publishersName "A coeur joie" ;
   * rdarelationships:electronicReproduction <http://gallica.bnf.fr/ark:/12148/bpt6k56248497> ;
   * rdarelationships:expressionManifested <http://data.bnf.fr/ark:/12148/cb39605922n#frbr:Expression> ;
   * rdarelationships:workManifested <http://data.bnf.fr/ark:/12148/cb13912399j#frbr:Work>,
   *   <http://data.bnf.fr/ark:/12148/cb139124107#frbr:Work>,
   *   <http://data.bnf.fr/ark:/12148/cb14017380x#frbr:Work> ;
   *   rdfs:seeAlso <http://catalogue.bnf.fr/ark:/12148/cb39605922n> ;
   * = <http://data.bnf.fr/ark:/12148/cb39605922n#about> .
   */
  static public function manifSql($record)
  {
    // lien à une œuvre
    if (isset($record["electronicReproduction"])) {
      preg_match("@<http://gallica.bnf.fr/ark:/12148/([^>]+)>@", $record["electronicReproduction"], $matches);
      $record['gallica'] = $matches[1];
    } else $record['gallica'] = null;

    if (!isset($record['type'])) {
      if (isset($record["scale"])) $record['type'] = "Map";
      else $record['type'] = null;
    }

    if (!isset($record['publisher'])) $record['publisher'] = null;
    /*
Parse description
In-12 — 2 vol. in-8° — Pièce — Non paginé [28] p. — XII-215-LXVII p. — 2 vol. (XVI-860, 497)-[2] p. de pl.
     */
    $record['pages'] = null;
    $record['size'] = null;
    if (!isset($record['description'])) $record['description'] = null;
    else {
      $desc = " ".strtr(mb_strtolower($record['description'], 'UTF-8'),
        array(
          '[' => '',
          ']'=>'',
          '(' => '',
          ')' => '',
      )
    )." ";

      // "XXIII-326 p"
      preg_match_all('/([0-9]+)(-[0-9IVXLC]+)? [pf]/', $desc, $matches, PREG_PATTERN_ORDER);
      if (preg_match("/pièce/u", $desc)) {
        $record['pages'] = 1;
      }
      else if (preg_match("/microfilm|microfiche/u", $desc)) {
        $record['type'] = 'Microfilm';
      }
      else if (count($matches[1]) > 0) {
        $record['pages'] = 0;
        foreach($matches[1] as $p) $record['pages'] += $p;
        if ($record['pages'] < 0) fwrite(STDERR, $record['ark'].' '.$desc);
      }
      // folio, format
      if (preg_match("/ in-([0-9]+)/u", $desc, $matches))
        $record['size'] = $matches[1];
      else if (preg_match("/ in-(fol\.|f°)/u", $desc, $matches))
        $record['size'] = 2;
    }
    if (!isset($record['dateline'])) {
      $record['dateline'] = null;
      $record['date'] = null;
    }
    try {
      // document normal, en sortir un identifiant nombre
      if (strpos($record['ark'], 'cb') === 0) {
        // ark, title, date, place, publisher, imprint, pages, size, description, id
        self::$q['doc']->execute(array(
          $record['ark'],
          $record['type'],
          $record['title'],
          $record['dateline'],
          $record['date'],
          $record['publisher'], // imprint, adresse éditoriale
          $record['place'],
          $record['publishersName'],
          $record['description'],
          $record['pages'],
          $record['size'],
          $record['gallica'],
          self::ark2id($record['ark'])
      ));
        self::$q['title']->execute(array(self::ark2id($record['ark']), $record['title']));
      }
      // archives, que faire ?
      else if (strpos($record['seeAlso'], 'archivesetmanuscrits')) {

      }
      else { // ???
        print_r($record);
      }
    }
    catch(Exception $e) {
      echo "  Doublon ? ".$record['ark']." ".$record['title']."\n";
    }
    if (isset($record['work']) && is_array($record['work'])) {
      foreach ($record['work'] as $k=>$v) {
        try {
          self::$q['version']->execute(array(self::ark2id($record['ark']), self::ark2id($k)));
        }
        catch (Exception $e) {
          echo "\n".$e->getMessage()."\n";
          echo "Œuvre introuvable — ".$record['ark'].":".self::ark2id($record['ark']).", ".$k.":".self::ark2id($k)."\n";
        }
      }
    }
  }

  /**
   * Boncler sur les fichiers "editions__expr" pour ramasser
   * des métadonnées de document (langue, type, sujets)
   *
   * <http://data.bnf.fr/ark:/12148/cb39605922n#frbr:Expression> a frbr:Expression ;
   *   dcterms:language <http://id.loc.gov/vocabulary/iso639-2/fre> ;
   *   dcterms:subject <http://data.bnf.fr/ark:/12148/cb119329384>,
   *     <http://data.bnf.fr/ark:/12148/cb11975995h>,
   *     <http://data.bnf.fr/ark:/12148/cb14623720f> ;
   *    dcterms:type dcmitype:Text ;
   *  = <http://data.bnf.fr/ark:/12148/cb39605922n#Expression> .
   */
  static public function expr($function="Databnf::exprSql") {
    // Traverser les expressions pour ramasser quelques autres métadonnées (langue ? type ?)
    $glob = dirname(__FILE__).'/editions/databnf_editions__expr_*.n3';
    echo $glob."\n";
    foreach(glob($glob) as $filepath) {
      // on prépare les requêtes
      self::$pdo->beginTransaction();
      self::$q['doctype'] = self::$pdo->prepare("UPDATE document SET type = ? WHERE id = ? AND type IS NULL");
      self::$q['doclang'] = self::$pdo->prepare("UPDATE document SET lang = ? WHERE id = ? AND lang IS NULL");
      self::fdo($filepath, $function);
      self::$pdo->commit();
    }
    echo " livre…";
    self::$pdo->exec("
      UPDATE document SET book = 1 WHERE type = 'Text' AND (pages IS NULL OR pages >= 45);
    ");
  }
  /**
   * Update d’un document avec des propriétés supplémentaires obtenues des fichiers expr*
   * lang, type, subject
   */
  static public function exprSql($record) {
    if (!isset($record['ark'])) return;
    $type = $record['type'];
    if (isset($record['subject']['cb119329384'])) $type = "Score";
    else if (strpos($record['ark'], 'cc') === 0) $type = "Archive";
    // ne pas modifier si déjà trouvé
    $id = self::ark2id($record['ark']);
    self::$q["doctype"]->execute(array($type, $id));
    self::$q["doclang"]->execute(array($record['language'], $id));
  }
  /**
   *  Normalisation des enregistrements
   */
  static function recnorm($record)
  {
    // lien à une œuvre
    if (isset($record["workManifested"])) {
      preg_match_all("@<(http://)?data.bnf.fr/ark:/12148/([^#]+)#frbr:Work>@", $record["workManifested"], $match_work);
      $record['work'] = array_flip($match_work[2]);
    }
    /*
    bnf-onto:firstYear 1700 ;
    dcterms:date "1700/1799" ;
    "18..-19.."
    */
    // date
    if (isset($record["date"])) {
      $record['dateline'] = $record['date'];
      if (!is_numeric($record['date'])) {
        $found = preg_match("@^(-?[0-9]+)/(-?[0-9]+)$@", $record['date'], $match_date);
        if ($found && ($match_date[2] - $match_date[1]) < 9) $record['date'] = $match_date[1];
        else $record['date'] = null;
      }
    }

    // titre
    if (isset($record["title"])) {
      // Attention aux guillemets dans les titres : Apollinaire et la "Démocratie sociale
      $record["title"] = stripslashes(
        preg_replace(
          '/^"|" *;?$/',
          '',
          trim($record["title"])
      )
    );
    } else $record["title"] = null;

    // publishersName
    if (isset($record["publishersName"])) {
      $record["publishersName"] = stripslashes(preg_replace('/^"|" *;?$/', '', trim($record["publishersName"])));
    } else $record["publishersName"] = null;

    // langue
    if (isset($record["language"])) {
      preg_match("@<http://id.loc.gov/vocabulary/iso639-2/([^>]+)>@", $record["language"], $match_lang);
      $record['language'] = $match_lang[1];
    } else $record['language'] = null;

    // type de documents
    if (isset($record["type"])) {
      preg_match("@dcmitype:([^ ]+)@", $record["type"], $match_type);
      // un seul type ? le dernier ?
      $record['type'] = $match_type[1];
    } else $record['type'] = null;

    // indexation sujet
    if (isset($record["subject"])) {
      preg_match_all("@<(http://)?data.bnf.fr/ark:/12148/([^>]+)>@", $record["subject"], $match_subject);
      // prendre les sujets comme clés de hashmap
      $record['subject'] = array_flip($match_subject[2]);
    } else $record["subject"] = array();

    // lieu de publication
    if (isset($record["placeOfPublication"])) {
      if ($pos = strpos($record["placeOfPublication"], ':')) $record["placeOfPublication"] = substr($record["placeOfPublication"], 0, $pos);
      if ($pos = strpos($record["placeOfPublication"], ' (')) $record["placeOfPublication"] = substr($record["placeOfPublication"], 0, $pos);
      if ($pos = strpos($record["placeOfPublication"], ',')) $record["placeOfPublication"] = substr($record["placeOfPublication"], 0, $pos);
      if ($pos = strpos($record["placeOfPublication"], '.')) $record["placeOfPublication"] = substr($record["placeOfPublication"], 0, $pos);
      if ($pos = strpos($record["placeOfPublication"], ' et')) $record["placeOfPublication"] = substr($record["placeOfPublication"], 0, $pos);
      $record['place'] = trim($record["placeOfPublication"], ' ";()[]');
      if ($record['place'] == 'S' || $record['place'] == 's') $record['place'] = null;
    } else $record['place'] = null;

    return $record;
  }

  /**
   *  Traiter un fichier
   *  en retirer un enregistrement normalisé
   *  déléguer le traitement de l’enregistrement à une fonction
   */
  static function fdo($filepath, $function)
  {
    $filename = basename($filepath);
    fwrite(STDERR, $filename."\n");
    $filestream = fopen($filepath, "r");
    $key = '';
    $value = '';
    $record = array();
    while (($line = fgets($filestream)) !== false) {
      $line = trim($line);
      // namespace declaration
      if (!$line || $line[0] == '@') continue;
      // enregistrer la ligne
      if (isset($record['n3'])) $record['n3'].="\n".$line;

      // fin d’un enregistrement, enregistrer
      if (preg_match('@> \.$@', $line, $matches)) {
        $record[$key] = $value; // dernière propriété en suspens
        $record = self::recnorm($record);
        call_user_func($function, $record);
        $record = null;
      }
      // debut d’un enregistrement qui nous intéresse
      else if (preg_match('@<http://data.bnf.fr/ark:/12148/([^#>]+)#([^>]+)> a [^ ]+ ;@', $line, $match_ark)) {
        $record = array();
        // enregistrer le texte rdf
        $record['n3'] = $line;
        $record['ark'] = $match_ark[1];
        $key = "";
        $value = "";
      }
      // pas encore d’enregistrement
      else if (!$record);
      // début d’une propriété
      else if (preg_match('@[a-z\-]+:([a-zA-Z\-]+) (.+)@', $line, $match_kv)) {
        if ($key) $record[ $key ] = $value;
        $key = $match_kv[1];
        $value = $value = stripslashes(preg_replace('/^"|"[, ;.]*$/', '',
          trim($match_kv[2])
      ));
      }
      // valeur en cours
      else {
        $value .= stripslashes(preg_replace('/^"|"[, ;.]*$/', '',
          trim($line)
      ));
      }
    }
  }
  /**
   *
   * <http://data.bnf.fr/ark:/12148/cb32726206b#frbr:Work> a frbr:Work ;
   *  rdfs:label "Bulletin des Ingénieurs des Arts et métiers de la Fédération des Groupes Alpes dauphinoises, Savoie, Haute-Savoie,  Drôme, Ardèche" ;
   *  bnf-onto:firstYear 1848 ;
   *  bnf-onto:lastYear 1865 ;
   *  dcterms:created "19.." ;
   *  dcterms:description "In-8" ;
   *  dcterms:language <http://id.loc.gov/vocabulary/iso639-2/fre> ;
   *  dcterms:publisher "Grenoble : [s.n.] , [19..-19..]" ;
   *  dcterms:title "Bulletin des Ingénieurs des Arts et métiers de la Fédération des Groupes Alpes dauphinoises, Savoie, Haute-Savoie, Drôme, Ardèche"@fr ;
   *  bibo:issn "2428-1719" ;
   *  rdagroup1elements:note "La couverture porte : \"Alpes Gadz'arts. Bulletin des ingénieurs...\""@fr ;
   *  rdagroup1elements:placeOfPublication "Grenoble" ;
   *  rdagroup1elements:publishersName "[s.n.]" ;
   *  = <http://data.bnf.fr/ark:/12148/cb32726206b#about> .
   */
  static function periodics()
  {

  }
  static function contributions()
  {
    $glob = dirname(__FILE__).'/contributions/databnf_contributions__expressions_*.n3';
    echo $glob."\n";
    foreach(glob($glob) as $filepath) {
      self::fcontributions($filepath);
    }
    Databnf::$pdo->exec("
-- Améliorer la récupération des partitions après les rôles
UPDATE document SET book = NULL, type = 'Score' WHERE type = 'Text' AND (SELECT count(*) FROM contribution WHERE contribution.document = document.id AND contribution.type = 20) > 1 AND (SELECT count(*) FROM contribution WHERE contribution.document = document.id AND contribution.type = 20) = (SELECT count(*) FROM contribution WHERE contribution.document = document.id);
-- Reporter la propriété book sur les contribution pour transmettre aux personnes
UPDATE contribution SET book = (SELECT book FROM document WHERE contribution.document = document.id);
");

// TODO posthum au moment où les données sont parsées
    Databnf::$pdo->exec("
-- valeur par défaut
UPDATE contribution SET posthum = NULL;
-- édition posthume
UPDATE contribution SET posthum = 1 WHERE date + 1 >= (SELECT deathyear FROM person WHERE id = contribution.person);
-- édition anthume
UPDATE contribution SET posthum = 0 WHERE date - 1 <= (SELECT deathyear FROM person WHERE id = contribution.person);
-- édition anthume d’un vivant
UPDATE contribution SET posthum = 0 WHERE (SELECT 1 FROM person WHERE id = contribution.person AND birthyear > 1920 AND deathyear IS NULL);
-- attention aux documents mal datés, genre compilation http://catalogue.bnf.fr/ark:/12148/cb42061926d Boutillier du Retail
UPDATE contribution SET posthum = NULL WHERE date - 15 <= (SELECT birthyear FROM person WHERE id = contribution.person);

    ");
    self::post_contrib();
  }
  /**
   * Met à jour la table des personnes avec des informations venant des contributions
   */
  static function post_contrib()
  {
    fwrite(STDERR, "UPDATE person.docs\n");
    Databnf::$pdo->exec("

UPDATE person SET
  -- nombre de documents
  docs=(SELECT count(*) FROM contribution WHERE person=person.id AND writes = 1),
  -- premier document publié du vivant de l’auteur
  doc1=(SELECT date FROM contribution WHERE person=person.id AND writes = 1 AND posthum = 0  ORDER BY date LIMIT 1),
  -- nombre de “livres”
  books=(SELECT count(*) FROM contribution WHERE person=person.id AND writes = 1 AND book = 1),
  -- premier livre > 50 p. publié du vivant de l’auteur
  opus1=(SELECT date FROM contribution WHERE person=person.id AND writes = 1 AND book = 1 AND posthum = 0  ORDER BY date LIMIT 1),
  -- nombre de livres publiés du vivant de l’auteur
  anthum=(SELECT count(*) FROM contribution WHERE person=person.id AND writes = 1 AND posthum = 0 AND book = 1),
  -- nombre de livres publiés après la mort de l’auteur
  posthum=(SELECT count(*) FROM contribution WHERE person=person.id AND writes = 1 AND posthum = 1 AND book = 1),
  -- nombre de livres
  books=(SELECT count(*) FROM contribution WHERE person=person.id AND writes = 1 AND book = 1)
;

-- erreurs de récupération de date
UPDATE person SET birthyear = NULL WHERE (opus1 - birthyear) > 100;

");

// TODO, corriger les dates dans les contributions ?
Databnf::$pdo->exec("
-- 100 - NULL = NULL
UPDATE person SET age1 = opus1 - birthyear;
UPDATE person SET writes=1 WHERE docs > 0;
-- TODO, corriger dans l’automate des conntributions
UPDATE document
  SET birthyear = (SELECT person.birthyear FROM contribution, person WHERE contribution.document = document.id
   AND contribution.writes = 1 AND contribution.person = person.id ORDER BY contribution.id LIMIT 1)
;
UPDATE document SET birthdec = CAST((birthyear / 10) AS INT) * 10;
UPDATE document
  SET deathyear = (SELECT person.deathyear FROM contribution, person WHERE contribution.document = document.id
   AND contribution.writes = 1 AND contribution.person = person.id ORDER BY contribution.id LIMIT 1)
;
UPDATE document
  SET gender = (SELECT person.gender FROM contribution, person WHERE contribution.document = document.id
   AND contribution.writes = 1 AND contribution.person = person.id ORDER BY contribution.id LIMIT 1)
;
UPDATE document SET posthum = 1 WHERE date > 1+deathyear;
UPDATE document SET posthum = 0 WHERE date < 1+deathyear;
UPDATE document SET posthum = NULL WHERE date < birthyear;
-- marqueur pour les morts
UPDATE document SET posthum = NULL WHERE deathyear = '???';
-- Erreur de code relation, ex http://catalogue.bnf.fr/ark:/12148/cb316472834
UPDATE document SET posthum = 1 WHERE (date - birthyear) > 100;
-- age vivant à la parution du document
UPDATE document SET age = date - birthyear WHERE posthum = 0;
UPDATE document SET agedec = (age / 10) * 10;
UPDATE document SET posthum = 1 WHERE (date - birthyear) > 100;
-- les vivants
UPDATE document SET posthum = 0 WHERE birthyear > 1920 AND date > birthyear AND deathyear IS NULL;


      ");
  }
  /**
   * databnf_person_authors__contributions_*.n3
   *
   * version 2016
   * <data.bnf.fr/ark:/12148/cb30006703s#frbr:Expression> bnfroles:r360 <data.bnf.fr/ark:/12148/cb12462059h#about> ;
   *   bnfroles:r70 <data.bnf.fr/ark:/12148/cb11997454f#about> ;
   *   marcrel:aut <data.bnf.fr/ark:/12148/cb11997454f#about> ;
   *   marcrel:edt <data.bnf.fr/ark:/12148/cb12462059h#about> ;
   *   dcterms:contributor <data.bnf.fr/ark:/12148/cb11997454f#about>,
   *       <data.bnf.fr/ark:/12148/cb12462059h#about> .
   *
   * version 2017
   * <http://data.bnf.fr/ark:/12148/cb30001195d#frbr:Expression> bnfroles:r360 <http://data.bnf.fr/ark:/12148/cb11864562t#about>,
   *         <http://data.bnf.fr/ark:/12148/cb11907770n#about> ;
   *     bnfroles:r70 <http://data.bnf.fr/ark:/12148/cb12647171s#about> ;
   *     marcrel:aut <http://data.bnf.fr/ark:/12148/cb12647171s#about> ;
   *     marcrel:edt <http://data.bnf.fr/ark:/12148/cb11864562t#about>,
   *         <http://data.bnf.fr/ark:/12148/cb11907770n#about> ;
   *     dcterms:contributor <http://data.bnf.fr/ark:/12148/cb11864562t#about>,
   *         <http://data.bnf.fr/ark:/12148/cb11907770n#about>,
   *         <http://data.bnf.fr/ark:/12148/cb12647171s#about> .   *
   * Relation entre un document et une ou plusieurs personnes.
   *
   */
  static function fcontributions($filepath)
  {
    self::$pdo->beginTransaction();
    $qdoc = self::$pdo->prepare("SELECT * FROM document WHERE id = ?");
    $qpers = self::$pdo->prepare("SELECT * FROM person WHERE id = ?");
    $qpersup = self::$pdo->prepare("UPDATE document SET pers = 1, birthyear = ?, deathyear = ?, posthum = ?, gender = ? WHERE id = ?");
    $q = self::$pdo->prepare("INSERT INTO contribution (document, person, role, date, posthum, writes, book) VALUES (?, ?, ?, ?, ?, ?, ?)");
    fwrite(STDERR, basename($filepath)."\n");
    $filestream = fopen($filepath, "r");
    // enregistreemnt à analyser
    $record = array();
    // récupérer le tableau des différentes relations
    $contribution = array();
    $expression = null;
    $role = null;
    $rerole = "@bnfroles:r([0-9]+)@";
    $repers = "@<http://data.bnf.fr/ark:/12148/([^#>]+)@";
    while (($line = fgets($filestream)) !== false) {
      // ligne pleine, ajouter à la notice en cours
      if(trim($line)) {
        $record[] = trim($line);
        continue;
      }
      // ligne blanche, traitement de la notice en cours
      // encore rien
      if (!count($record)) continue;
      // tout sur une ligne
      $line = trim(implode(" ", $record));
      // remettre l'enregistrement à 0
      $record = array();
      $needle = "#Expression>";
      $pos = strpos($line, $needle);
      if (!$pos) {
        // debug pour repérer les enregistrements bizarres
        if($line[0] == '<') fwrite(STDERR, $line." pas de $needle ". "\n");
        continue;
      }
      $expression = substr($line, $pos - 11, 11);
      $line = substr($line, $pos+strlen($needle));
      $qdoc->execute(array(self::ark2id($expression)));
      $doc =$qdoc->fetch(PDO::FETCH_ASSOC);
      if (!$expression                       // rien trouvé
       || strpos ($expression, 'cc') === 0  // archive, on passe
       || !$doc                               // notice spectacle ou périodique
      ) {
        continue;
      }
      // eploser les roles et boucler dessus
      $contribs = explode(" ;", $line);
      $writelist = array();
      foreach($contribs as $l) {
        // ne prendre que les roles bnf (marcrel et dcterms:contributor sont des redondances)
        if (!preg_match($rerole, $l, $matches)) {
          continue;
        }
        $role = $matches[1];
        preg_match_all($repers, $l, $matches);
        // boucler sur les personnes
        foreach ($matches[1] as $persark) {
          $qpers->execute(array(self::ark2id($persark)));
          $pers = $qpers->fetch(PDO::FETCH_ASSOC);
          // auteur inconnu comme personne, auteur organisation ?
          if (!$pers) {
            continue;
          }
          // si le document est de type son, et le rôle=990 ou 980, on dit que c’est compositeur (220)
          if ($doc['type'] == 'Sound' && ($role == 980 || $role == 990)) $role=220;
          $writes = null;
          if ($doc['type'] && $doc['type'] != 'Text') $writes = null;
          else if (isset(self::$roles['writes'][$role])) {
            $writes = 1;
            if (isset($writelist[$persark])) continue; // déjà enregistré comme auteur principal
            $writelist[$persark] = true;
          }

          // document posthume ?
          $posthum = null;
          // ajouter des informations au document sur l’auteur principal si pas déjà renseigné, priorité aux femmes
          if ($writes && (!$doc['pers'] || $pers['gender'] == 2)) {
            // Homère, naissance et mort null
            if (is_null($pers['birthyear']) && is_null($pers['deathyear']))
              $posthum = 1;
            // auteur vivant, pas de documents posthumes
            else if (is_null($pers['deathyear']))
              $posthum = null;
            // auteur certainement mort mais sans date (???)
            else if (!is_numeric($pers['deathyear']))
              $posthum = null;
            // date de document après la date de mort (mrge de 1 an)
            else if ($doc['date'] > $pers['deathyear']+1)
              $posthum = 1;
            $qpersup->execute(array($pers['birthyear'], $pers['deathyear'] , $posthum, $pers['gender'], $doc['id']));
          }
          // insert
          $ins = array(
            $doc['id'],
            $pers['id'],
            $role,
            $doc['date'],
            $posthum,
            $writes,
            $doc['book'],
          );
          try {
            $q->execute($ins);
          }
          catch (Exception $e) {
            // print_r($e);
            print_r($line);
          }
        }
      }
    }
    fclose($filestream);
    self::$pdo->commit();
  }



  /**
   * Œuvres
   */
  static public function works()
  {
    $glob = "works/databnf_works__frbr_*.n3";
    foreach(glob($glob) as $filepath) {
      self::fworks($filepath);
    }
    self::$pdo->exec("UPDATE work SET versions=(SELECT count(*) FROM version WHERE work=work.id AND date > 0)");
  }

  /**
   * Chargement d’une liste d’œuvres
   */
  static public function fworks($filename) {
    fwrite(STDERR, $filename. "\n");
    $res = fopen($filename, 'r');
    $work = null;
    $cols = array("id", "ark", "title", "date", "lang");
    $sql = "INSERT INTO work (".implode(", ", $cols).") VALUES (".rtrim(str_repeat("?, ", count($cols)), ", ").");";
    // self::$pdo->beginTransaction();
    $q = self::$pdo->prepare($sql);
    $q2 = self::$pdo->prepare(
      "INSERT INTO creation (work, person) VALUES (?, ?);"
  );
    // de quoi ajouter le texte de la notice, pour debug
    $txt = array();
    $count = 0;
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      $line = preg_replace('/"@[a-z]+/', '', $line); // suppression des indications de langue
      // début d’œuvre, initialiser l’enregistreur
      if (!$work && preg_match('@^<http://data.bnf.fr/ark:/12148/([^/# ]+)#about>@', $line, $matches)) {
        // un tableau d’exactement le nombre de cases que ce que l’on veut insérer
        $work = array_combine($cols, array_fill(0, count($cols), null));
        $work['ark'] = $matches[1];
        $work['id'] = self::ark2id($work['ark']);
        $txt = array();
        $lastkey = null;
        $suf = null;
        $work['creator'] = array();
        $work['subject'] = array();
        continue;
      }
      // ici on laisse passer, théoriquement ligne vide entre deux œuvres
      if (!$work) continue;
      // capture de la clé
      preg_match('@^([^ :]+:[^ :]+)(.+)@', $line, $matches);
      if (isset($matches[1])) $key = $matches[1];
      else $key = null;
      if (isset($matches[2])) $value = trim($matches[2], "\" \t,.;");
      else $value = null;
      if (!$key && 'dcterms:description' == $lastkey) {
        if (!isset($work['description'])) $work['description'] = "";
        $work['description'] .= trim($line, "\" \t,.;");
      }
      else if ('rdfs:label' == $key) { // inutile pour l’instant
        $label = $value;
        if (!$work['title']) $work['title'] = $value;
      }
      else if ('dcterms:title' == $key) {
        if (!$work['title']) $work['title'] = $value;
        $pos = strrpos($value, ':');
        if ($pos > 2) {
          $suf = trim(substr($value, $pos + 1));
          /*
          if ($work['type']);
          else if ("film" == $type) $work['type'] = 'video';
          */
        }
      }
      else if ('dcterms:creator' == $key) {
        $work['creator'][] = $value;
      }
      else if ('bnf-onto:subject' == $key) {
        $work['subject'][] = $value;
      }
      else if ('dcterms:language' == $key) {
        preg_match("@<http://id.loc.gov/vocabulary/iso639-2/([^>]+)>@", $value, $match_lang);
        $work['lang'] = $match_lang[1];
      }
      else if ('bnf-onto:firstYear' == $key) {
        $work['date'] = 0+trim($value);
        if (!$work['date']) $work['date'] = null;
      }

      /* Attraper une date
    bnf-onto:firstYear 1984 ;
    bnf-onto:subject "Littératures" ;
    dcterms:creator <http://data.bnf.fr/ark:/12148/cb12060490k#foaf:Person> ;
    dcterms:date "1984" ;
    dcterms:description "Roman"@fr ;
    dcterms:language <http://id.loc.gov/vocabulary/iso639-2/spa> ;
    dcterms:subject <http://dewey.info/class/800/> ;
    dcterms:title "El desfile del amor"@es ;
    rdagroup1elements:dateOfWork <http://data.bnf.fr/date/1984/> .

    dcterms:description "Divertimento en 2 parties : \"Andantino\" et \"A minuet\""@fr ;
    description sur plusieurs lignes
       */

      $lastkey = $key;
      // fin d’assertion, insérer l’enregistrement
      if ($work && preg_match("/> \.$/", $line)) {
        // $work['creator'] = implode($work['creator'], ' ; ');
        $work['subject'] = implode($work['subject'], '. ');
        if (count($work['creator'])) {
          foreach($work['creator'] as $value) {
            if (preg_match("@<(http://)?data.bnf.fr/ark:/12148/([^#]+)#foaf:Person>@", $value, $match_pers)) {
              $q2->execute(array($work['id'], self::ark2id($match_pers[2])));
            }
          }
        }
        $record = array($work['id'], $work['ark'], $work['title'], $work['date'], $work['lang']);
        try {
          $q->execute($record);
        } catch (Exception $e) {
          print_r($e);
          print_r($record);
          exit();
        }
        $work = null;
      }
    }
  }

  /**
   * Chargement des personnes
   * Pas encore de prise en compte des homonymes
   */
  static public function persons()
  {
    include(dirname(__FILE__).'/given.php');
    self::$given = $given;
    include(dirname(__FILE__)."/frtr.php"); // crée une variable $frtr
    self::$frtr = $frtr;
    $glob = dirname(__FILE__)."/person/databnf_person_authors__foaf_*.n3";
    foreach(glob($glob) as $file) {
      self::catfoaf($file);
    }
    Databnf::$pdo->exec("

    -- Auteurs certainement morts, mais on ne sait pas quand
    UPDATE person SET deathyear = '???'
    WHERE deathyear IS NULL AND birthyear < 1920
    ;
    -- Normalisation Paris. NULL = pas d’info. 0 = ailleurs. 1 = Paris.
    UPDATE person SET birthparis = 0
    WHERE birthplace IS NOT NULL
    ;
    UPDATE person SET deathparis = 0
    WHERE deathplace  IS NOT NULL
    ;
    UPDATE person SET birthparis = 1
    WHERE birthplace LIKE '%paris%'
    ;
    UPDATE person SET deathparis = 1
    WHERE deathplace LIKE '%paris%'
    ;
    UPDATE person SET fr = 1 WHERE fr IS NULL AND lang = 'fr';
    UPDATE person SET fr = 1 WHERE fr IS NULL AND country = 'fr';

    ");
  }
  /**
   * Traitement d’un fichier de personnes
   */
  public static function catfoaf($file)
  {
    fwrite(STDERR, basename($file));
    $res = fopen($file, 'r');
    $person = null;
    $cols = array("id", "ark", "sort", "name", "family", "given", "ogender", "gender", "birth", "death", "birthyear", "deathyear", "birthplace", "deathplace", "age", "lang", "country", "note");
    $sql = "INSERT INTO person (".implode(", ", $cols).") VALUES (".rtrim(str_repeat("?, ", count($cols)), ", ").");";
    self::$pdo->beginTransaction();
    $insperson = self::$pdo->prepare($sql);
    self::$q['persdewey'] = self::$pdo->prepare("INSERT INTO persdewey (person, dewey) VALUES (?, ?)");
    // de quoi ajouter le texte de la notice, pour debug
    $txt = array();
    $count = 0;
    while (($line = fgets($res)) !== false) {
      $line = trim($line);
      // fin d’assertion, insérer l’auteur
      if ($person && preg_match("/\.$/", $line)) {
        if(!$person['name'] && $person['family']) $person['name'] = trim($person['given']." ".$person['family']);
        // gender
        if (!$person['gender'] && $person['given']) {
          $key = mb_strtolower($person['given']);
          $a = preg_split('@[ -]+@', $key);
          $key = $a[0];
          if (isset(Databnf::$given[$key])) $person['gender'] = Databnf::$given[$key];
          // else echo $key."\n";
        }
        // age
        if ($person['birthyear'] && $person['deathyear']) {
          $person['age'] = $person['deathyear'] - $person['birthyear'];
          if ($person['age']<2 || $person['age']>115) { // erreur dans les dates, siècles sans point 1938-18=1920 ans
            $person['age'] = $person['birthyear'] = $person['deathyear'] = null;
          } // ne pas compter les enfants comme auteur
          if ($person['age'] < 20) $person['age'] = null;
        }
        $person['sort'] = strtr($person['family'].$person['given'], self::$frtr);
        $person['id'] = self::ark2id($person['ark']);
        /* Pour test, semble OK
        if($person['birthyear'] === null) {
          print_r($person);
          echo implode("\n", $txt);
        }
        */
        try {
          $insperson->execute(array_values($person));
          foreach($dewey as $code) {
            self::$q['persdewey']->execute(array($person['id'], $code));
          }
          $count++;
        }
        catch (Exception $e) {
          echo "\n".$e->getMessage()."\n";
          print_r($person);
          echo implode("\n", $txt);
        }
        $person = null;
        $txt = null;
      }

      // capture de la clé
      preg_match('@^([^ :]+:[^ :]+)(.+)@', $line, $matches);
      if (isset($matches[1])) $key = $matches[1];
      else $key = null;
      if (isset($matches[2])) {
        $value = stripslashes(
          preg_replace(
            '/^"|"[, ;.]*$/',
            '',
            trim($matches[2])
        )
      );
      }
      else $value = null;

      // début d’auteur, initialiser l’enregistreur
      if (!$person && preg_match('@/([^/# ]+)#about> a foaf:Person ;@', $line, $matches)) {
        $person = array_combine($cols, array_fill(0, count($cols), null));
        $person['ark'] = $matches[1];
        $dewey = array();
        $txt = array();
      }
      // ici on laisse passer
      else if (!$person);
      else if (preg_match('@foaf:name "([^"]+)"@', $line, $matches)) {
        $person['name'] = $matches[1];
      }
      else if (preg_match('@foaf:familyName "([^"]+)"@', $line, $matches)) {
        $person['family'] = $matches[1];
      }
      else if (preg_match('@foaf:givenName "([^"]+)"@', $line, $matches)) {
        $person['given'] = $matches[1];
      }
      else if (preg_match('@foaf:gender "([^"]+)"@', $line, $matches)) {
        $person['ogender'] = $matches[1];
        if ($matches[1] == "male") $person['gender'] = 1;
        else if ($matches[1] == "female") $person['gender'] = 2;
        else echo "Gender ? ".$matches[1]."\n";
      }
      else if (preg_match('@bio:birth "(((- *)?[0-9\.]+)[^"]*)"@', $line, $matches)) {
        $person['birth'] = $matches[1];
        if (strpos($matches[2], '.') !== false);
        else if (is_numeric($matches[2])) $person['birthyear'] = $matches[2];
      }
      else if (preg_match('@bio:death "(((- *)?[0-9\.]+)[^"]*)"@', $line, $matches)) {
        $person['death'] = $matches[1];
        if (strpos($matches[2], '.') !== false);
        else if (is_numeric($matches[2])) $person['deathyear'] = $matches[2];
      }
      // bnf-onto:firstYear 1919 ;
      // bnf-onto:lastYear 2006 ;
      else if (preg_match('@bnf-onto:firstYear[^0-9\-]*((- *)?[0-9\.]+)@', $line, $matches)) {
        if ($person['birthyear']); // la date en bio prend le dessus
        else if (strpos($matches[1], '.') !== false);
        else if (!is_numeric($matches[1]));
        else $person['birthyear'] = $matches[1];
      }
      else if (preg_match('@bnf-onto:lastYear[^0-9\-]*((- *)?[0-9\.]+)@', $line, $matches)) {
        if ($person['deathyear']); // la date en bio prend le dessus
        else if (strpos($matches[1], '.') !== false);
        else if (!is_numeric($matches[1]));
        else $person['deathyear'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:placeOfBirth "([^"]+)"@', $line, $matches)) {
        $person['birthplace'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:placeOfDeath "([^"]+)"@', $line, $matches)) {
        $person['deathplace'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:countryAssociatedWithThePerson <http://id.loc.gov/vocabulary/countries/([^>/]+)@', $line, $matches)) {
        $person['country'] = $matches[1];
      }
      else if (preg_match('@rdagroup2elements:languageOfThePerson <http://id.loc.gov/vocabulary/iso639-2/([^>/]+)@', $line, $matches)) {
        $person['lang'] = $matches[1];
      }
      // pour l’instant, un par ligne
      else if (preg_match('@<http://dewey.info/class/([0-9]+)@', $line, $matches)) {
        $dewey[] = $matches[1];
      }
      // rdagroup2elements:biographicalInformation "
      else if ($key == "rdagroup2elements:biographicalInformation") {
        $person['note'] = $value;
      } // note biblio sur plusieurs lignes
      else if ($key == null && $lastkey == "rdagroup2elements:biographicalInformation") {
        // guillemets échappés pour titres
        $person['note'] .= ".\n— ".stripslashes(
          preg_replace(
            '/^"|"[, ;.]*$/',
            '',
            trim($line)
        )
      );
      }

      if (is_array($txt)) $txt[] = $line;
      $lastkey = $key;
    }
    self::$pdo->commit();
    fwrite(STDERR, "  --  ".$count." persons\n");
  }

  /**
   * Chargement des studies
   * Drôles de docs http://data.bnf.fr/ark:/12148/cb38302217r/1
   */
  static public function studies()
  {
    $glob = "study/databnf_study_*.n3";
    foreach(glob($glob) as $filename) {
      fwrite(STDERR, $filename. "\n");
      $sql = "INSERT INTO study (document, entity) VALUES (?, ?);";
      self::$pdo->beginTransaction();
      self::$q['study'] = self::$pdo->prepare($sql);
      $res = fopen($filename, 'r');
      $document = null;
      while (($line = fgets($res)) !== false) {
        if (preg_match('@^<http://data.bnf.fr/ark:/12148/([a-z0-9]+)@', $line, $matches)) {
          $document = self::ark2id($matches[1]);
        }
        if (preg_match('@ <http://data.bnf.fr/ark:/12148/([a-z0-9]+)#about>@', $line, $matches)) {
          $entity = self::ark2id($matches[1]);
          try {
            self::$q['study']->execute(array($document, $entity));
          } catch (Exception $e) {
            // print $e;
            // print $document." ".$entity."\n";
          }
        }
      }
      self::$pdo->commit();
    }
  }

  /**
   * Les identifiants BNF sont normalement sûrs, voyons
   */
   public static function ark2id($cote) {
     return 0+substr($cote, 2, -1);
   }

}


?>
