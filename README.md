# potion-diceroller

The *potion-diceroller* is an extension for [voodoo-sheetgen](https://github.com/Barthak/voodoo-sheetgen). 

## Setup

Install the `WikiDiceRoller.php` file into your spellbook/Sheetgen/Potions directory. 

Install the `diceroller.html` file into your spellbook/Sheetgen/templates directory.

In your `wiki.ini` file (usually in spellbook/Wiki/conf) add:
```
[potions]
...
Sheetgen_WikiDiceRoller = On
```

Now, the database needs to be updated with the Diceroller tables. You can see the SQL output by surfing to http://yourvoodooinstance/setup/Controller/Wiki

Execute the SQL so the tables are created. Now you can use the potion within your wiki pages by using `[[Sheetgen_WikiDiceRoller]]`

Now add the following to your `sheetgen.ini` file (usually in spellbook/Sheetgen/conf):
```
[diceroller]
use_sheet_characters = On ; Select characters that are created from the sheet generator
allow_any_character = On ; Allow input of any character
mutually_exclusive = On ; Only allow usage of selectable sheet-characters or text-input character
variable_difficulty = On ; Let the roller decide the difficulty
default_difficulty = 8 ; The default value for difficulty
```
