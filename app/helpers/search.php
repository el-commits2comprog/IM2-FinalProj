<?php
// [M2] Search helper: keyword cleaning + synonym expansion. M4 reuses it for matching
declare(strict_types=1);

const SEARCH_SYNONYMS = [

    ['phone', 'cellphone', 'mobile', 'smartphone'],
    ['laptop', 'computer'],
    ['earphones', 'earbuds', 'headphones', 'headset'],
    ['charger', 'charging cable', 'adapter', 'phone charger', 'USB cable', 'cord'],

    ['wallet', 'purse', 'handbag', 'pouch'],
    ['id', 'identification', 'card', 'school id'],
    ['bag', 'backpack', 'knapsack', 'purse', 'sling bag', 'handbag', 'shoulder bag'],
    ['key', 'keys', 'keychain'],
    
    ['jacket', 'coat', 'hoodie', 'windbreaker'],
    ['shirt', 't-shirt', 'tee'],
    ['shoes', 'sneakers', 'footwear'],

    ['notebook', 'notepad', 'journal', 'binder'],
    ['pen', 'ballpoint', 'marker'],
    ['pencil', 'mechanical pencil'],
    ['calculator', 'calc'],

    ['umbrella', 'brolly'],
    ['water bottle', 'tumbler', 'flask'],
    ['glasses', 'eyeglasses', 'spectacles'],

];

const SEARCH_STOPWORDS = ['a', 'an', 'the', 'of', 'my', 'and', 'or', 'in', 'on', 'with'];

function search_keywords(string $text): array
{
    $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    $words = array_filter($words, static fn($w) => !in_array($w, SEARCH_STOPWORDS, true));
    return array_slice(array_values(array_unique($words)), 0, 5);
}

function search_expand(string $word): array
{
    foreach (SEARCH_SYNONYMS as $group) {
        if (in_array($word, $group, true)) {
            return $group;
        }
    }
    return [$word];
}

function search_like(string $word): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word) . '%';
}
