<?php

namespace App\Presenters;

final class HomepagePresenter extends \Nette\Application\UI\Presenter
{
    private $entityName;

    private $properties = [];

    private static $intTypes = [
        'smallint',
        'mediumint',
        'int',
        'bigint'
    ];

    private static $floatTypes = [
        'float',
        'double'
    ];

    private static $stringTypes = [
        'char',
        'varchar',
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'enum',
        'set',
        'timestamp',
        'time',
        'year'
    ];

    private static $boolTypes = [
        'tinyint'
    ];

    private static $datetimeTypes = [
        'date',
        'datetime'
    ];


    public function renderDefault()
    {
        $this->template->entityName = $this->entityName;
        $this->template->properties = $this->properties;
    }


    public function createComponentSqlForm()
    {
        $form = new \Nette\Application\UI\Form;

        $form->addTextArea('sql', 'SQL', 200, 20)
            ->setRequired();

        $form->addSubmit('send', 'Odeslat');

        $form->addButton('copy', 'Kopírovat výstup')
            ->setHtmlAttribute('onclick', 'copyOutput()');

        $form->onSuccess[] = [$this, 'sqlFormSuccess'];

        return $form;
    }


    public function sqlFormSuccess(\Nette\Application\UI\Form $form, \Nette\Utils\ArrayHash $values)
    {
        $unknownTypesArray = $this->generateEntity($values->sql);

        $this->flashMessage('Entita úspěšně vygenerována', 'success');

        $unknownTypes = implode(', ', $unknownTypesArray);

        if(sizeof($unknownTypesArray) == 1)
        {
            $this->flashMessage('Nepodporovaný typ (' . $unknownTypes . '), zkontrolujte výsledný kód', 'warning');
        }
        elseif(sizeof($unknownTypesArray) > 1)
        {
            $this->flashMessage('Nepodporované typy (' . $unknownTypes . '), zkontrolujte výsledný kód', 'warning');
        }
    }


    private function generateEntity(string $sql)
    {
        $tableName = [];

        preg_match('/TABLE `(.+)`/', $sql, $tableName);

        $entityName = ucwords($tableName[1], '_');
        $entityName = str_replace('_', '', $entityName);
        $entityName .= 'Entity';

        $this->entityName = $entityName;

        $columns = [];

        preg_match_all('/  `(\w+)` (\w+)(?:\(?.+\))*(?:.*(NOT))?(?:.*(AUTO_INCREMENT))?/', $sql, $columns, PREG_SET_ORDER);

        $properties = [];

        $unknownTypes = [];

        for($i = 0; $i < sizeof($columns); $i++)
        {
            $properties[$i] = "\t";

            if(!empty($columns[$i][4]))
            {
                $properties[$i] .= '#[\ModulIS\Attribute\Readonly]' . PHP_EOL . "\t";
            }

            $properties[$i] .=  'public ';

            if(empty($columns[$i][3]))
            {
                $properties[$i] .= '?';
            }

            if(in_array($columns[$i][2], self::$intTypes))
            {
                $properties[$i] .= 'int';
            }
            elseif(in_array($columns[$i][2], self::$floatTypes))
            {
                $properties[$i] .= 'float';
            }
            elseif(in_array($columns[$i][2], self::$stringTypes))
            {
                $properties[$i] .= 'string';
            }
            elseif(in_array($columns[$i][2], self::$boolTypes))
            {
                $properties[$i] .= 'bool';
            }
            elseif(in_array($columns[$i][2], self::$datetimeTypes))
            {
                $properties[$i] .= '\Nette\Utils\DateTime';
            }
            else
            {
                $properties[$i] .= ' ' . $columns[$i][2];

                if(!in_array($columns[$i][2], $unknownTypes))
                {
                    $unknownTypes[] = $columns[$i][2];
                }
            }

            $properties[$i] .= ' $' . $columns[$i][1] . ';' . PHP_EOL;
        }

        /* Strip last EOL */
        $properties[array_key_last($properties)] = rtrim($properties[array_key_last($properties)]);

        $this->properties = $properties;

        $this->redrawControl('entity');

        return $unknownTypes;
    }
}
