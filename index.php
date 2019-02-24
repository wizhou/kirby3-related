<?php
/**
 * Kirby 3 Related Pages Plugin
 *
 * @version   0.8.0
 * @author    Sonja Broda <info@texniq.de>
 * @copyright Sonja Broda <info@texniq.de>
 * @link      https://github.com/texnixe/kirby-related
 * @license   MIT
 */


Kirby::plugin('texnixe/related', [
    'options' => [
        'cache' => true,
        'expires' => (60*24*7), // minutes
        'defaults' => [
            'searchField'      => 'tags',
            'matches'          => 1,
            'delimiter'        => ',',
            'languageFilter'   => false,
        ]
    ],
    'pageMethods' => [
        'related' => function (array $options = []) {
            return Related::getRelated($this, $options);
        }
    ],
    'fileMethods' => [
        'related' => function (array $options = []) {
            return Related::getRelated($this, $options);
        }
    ],
    'hooks' => [
        'page.update:after' => function() {
            Related::flush();
        },
        'page.create:after' => function() {
            Related::flush();
        },
        'file.create:after' => function() {
            Related::flush();
        },
        'page.update:after' => function() {
            Related::flush();
        }
    ]
]);


class Related
{

    private static $indexname = null;

    private static $cache = null;

    private static function cache(): \Kirby\Cache\Cache
    {
        if (!static::$cache) {
            static::$cache = kirby()->cache('texnixe.related');
        }
        // create new index table on new version of plugin
        if (!static::$indexname) {
            static::$indexname = 'index'.str_replace('.', '', kirby()->plugin('texnixe/related')->version()[0]);
        }
        return static::$cache;
    }

    public static function flush()
    {
        return static::cache()->flush();
    }

    public static function data($basis, $options = [])
    {
        // new empty collection
        $related = static::getClassName($basis, []);

        $defaults = option('texnixe.related.defaults');
        // add the default search collection to defaults
        $defaults['searchCollection'] = $basis->siblings(false);

        // Merge default and user options
        $options = array_merge($defaults, $options);

        // define variables
        $searchCollection = $options['searchCollection'];
        $matches          = $options['matches'];
        $searchField      = str::lower($options['searchField']);
        $delimiter        = $options['delimiter'];
        $languageFilter   = $options['languageFilter'];

         // get search items from active basis
         $searchItems     = $basis->{$searchField}()->split(',');
         $noOfSearchItems = count($searchItems);

        if($noOfSearchItems > 0) {
            // no. of matches can't be greater than no. of searchItems
            $matches > $noOfSearchItems? $matches = $noOfSearchItems: $matches;

            for($i = $noOfSearchItems; $i >= $matches; $i--) {
            $relevant{$i} = $searchCollection->filter(function($b) use($searchItems, $searchField, $delimiter, $i) {
                return count(array_intersect($searchItems, $b->$searchField()->split($delimiter))) == $i;
            });

            $related->add($relevant{$i});
        }

        // filter collection by current language if $languageFilter set to true
        if(kirby()->multilang() === true && $languageFilter === true) {
            $related = $related->filter(function($p) {
                return $p->translation(kirby()->language()->code())->exists();
            });
        }

        }
        return $related;

    }

    public static function getClassName($basis, $items = '')
    {
        if(is_a($basis, 'Kirby\Cms\Page')) {
            return pages($items);
        }
        if(is_a($basis, 'Kirby\Cms\File')) {
            return new Files($items);
        }
    }

    public static function getRelated($basis, $options = [])
    {
        $collection = $options['searchCollection']?? $basis->siblings(false);

        // try to get data from the cache, else create new
        if($response = static::cache()->get(md5($basis->id().$options))) {
            $data = $response['data'];
            $related = static::getClassName($basis, array_keys($data));
        } else {
            $related = static::data($basis, $options);
        }

        static::cache()->set(
            md5($basis->id() . $options),
            $related,
            option('texnixe.related.expires')

        );

        return $related;
    }
}


