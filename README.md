# Ethode Scriots

This is kinda hacked together for personal use, but at Casy's request, I put it up here.

If you have any ideas or suggestions, feel free to do a pull request.

Globally, you need to do the following:

1. Download composer & run `composer install` in this directory
1. Copy `config.example.php` to `config.php`

### Daily Standup Script

To get this part going (after doing the steps above):

2. Go here and get an API key: https://trello.com/1/appKey/generate
3. Update the `key` and `secret` in `config.php`
4. Run the PHP file in the server (`sudo php -S localhost:80` works, just make sure whatever you use is on port 80)
5. Open the script in the browser, and authorize the app when prompted
6. You should update `templateURL` with whatever you fancy.  You can see the example one (my personal standup) 
7. You can now use this script both in the CLI and in the browser if you wish.


**Notes:**

* You should play around with `boardsmatch` config option if it takes too long to run.
* It should skip all archived boards, cards, and lists
* It only will return cards assigned to YOU
* Today:
 * This script scrubbs ALL of your boards for anything that is in `Doing` or in `Today` that you personally are assigned to
* Yesterday:
 * Scrubbs each board activity for any card moved to either `Done` or `QA`


This is kinda nice too for your `~/.bash_profile`: 

```
alias standup='php -f ~/Sites/local.ethode/standup.php'
alias eschedule='php -f ~/Sites/local.ethode/schedule.php'
```

### Daily Schedules

Be sure to do those 2 steps listed above, and to define the two config options in `config.php`

