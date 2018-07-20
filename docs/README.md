# Magento 2 - Fix broken multistore category urls

This fork of [peterjaap/magento2-regenurl](https://github.com/peterjaap/magento2-regenurl) adds a command to fix broken category urls on Magento 2.2.x. You can find more info about the issue here: [#16202](https://github.com/magento/magento2/issues/16202)

**We've used this solution once, on one of our Magento stores running Magento 2.2.4, with over 30 store views using four languages. Never try this on production without testing!**

## The problem

When you're running a Magento 2.2 store with multiple store views, let's say three stores in different languages (Dutch, French and German), sometimes the category urls will start to deform like this:.

- www.store.de/dutch-word/german-word
- www.store.fr/german-word/french-word
- www.store.nl/french-word/dutch-word

### The solution
We've tested this solution on one of our Magento stores running Magento 2.2.4, with over 30 store views using four languages

1. Identify the problem by running the following SQL query:
```sql
SELECT * FROM `url_rewrite` WHERE `entity_type` LIKE 'category' AND `entity_id` IN (category, ids, here) AND `store_id` = 8 ORDER BY `url_rewrite_id`
```
In our case this resulted in multiple bad 301 redirects, plus the wrong category urls.
2. Delete the url_rewrites:
```sql
DELETE FROM `url_rewrite` WHERE `entity_type` LIKE 'category' AND `entity_id` IN (category, ids, here) AND `store_id` = 8 ORDER BY `url_rewrite_id`
```
3. Run the command `php bin/magento regenerate:product:path -s storeId cids 1 2 3 5`
4. Run the command `php bin/magento regenerate:product:url -s storeId cids 1 2 3 5`

### Automating the solution
I've created a command that will do this for you automatically:

`php bin/magento regenerate:category:tree --category categoryId --store storeId`

In our case it didn't work to regenerate one of the deepest level subcategories. We had to use one of the top level categories to make this work. The command will automatically fix the parent and the child categories.

Output:

![Output screenshot](https://github.com/Tjitse-E/magento2-regenurl/blob/master/docs/console-screenshot.jpg)

Before:

![Before screenshot](https://github.com/Tjitse-E/magento2-regenurl/blob/master/docs/wrong-urls.png)

After:

![After screenshot](https://github.com/Tjitse-E/magento2-regenurl/blob/master/docs/fixed-urls.png)

# Install
Using Composer;

```sh
composer config repositories.regenurl vcs git@github.com:Tjitse-E/magento2-regenurl.git
composer require iazel/module-regen-product-url
php bin/magento setup:upgrade
```

Or download and copy the `Iazel` directory into `app/code/` and run `php bin/magento setup:upgrade`.
