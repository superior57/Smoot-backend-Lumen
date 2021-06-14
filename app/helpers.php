<?php

function cleanStringTitle($value)
{
    return \Illuminate\Support\Str::title(trim(preg_replace("/\s+/", " ", $value), " "));
}

function makeSecondLetterCapitalize($value)
{
    $value[1] = strtoupper($value[1]);
    return $value;
}

function cleanStringTitleWithDictionary($value, $dictionary)
{
    $value = removeAllowedSpecialCharactersAtStatAndEnd($value);
    $value = \Illuminate\Support\Str::lower(preg_replace("/\s+/", " ", $value));
    if (\Illuminate\Support\Str::contains($value, $dictionary)) {
        $replacedValue = "";
        $words = explode(" ", $value);
        foreach ($words as $word) {
            if (in_array($word, $dictionary)) {
                $replacedValue .= makeSecondLetterCapitalize($word) . " ";
            } else {
                $replacedValue .= $word . " ";
            }
        }
        return trim($replacedValue, " ");
    } else {
        return \Illuminate\Support\Str::title(trim(preg_replace("/\s+/", " ", $value), " "));
    }
}

function cleanString($value)
{
    return trim(preg_replace("/\s+/", " ", $value));
}

function cleanUsername($value)
{
    return trim(preg_replace("/-+/", "-", $value), "-");
}

function generateSlug($value)
{
    $value = str_replace("&", "-and-", $value);
    $value = str_replace("+", "-plus-", $value);
    $value = str_replace(",", "-", $value);
    $value = str_replace("\"", "-inch-", $value);
    $value = str_replace(".", "-point-", $value);
    $value = str_replace("/", "-slash-", $value);
    return \Illuminate\Support\Str::slug($value, "-");
}

function returnFirstCharacterCapitalized($value)
{
    $value = removeAllowedSpecialCharactersAtStatAndEnd($value);
    $value = \Illuminate\Support\Str::lower($value);
    return \Illuminate\Support\Str::ucfirst($value);
}

function removeAllowedSpecialCharactersAtStatAndEndForLookupValue($tags)
{
    $validatedTags = [];
    foreach ($tags as $tag) {
        $tag = removeAllowedSpecialCharactersAtStatAndEnd($tag);
        array_push($validatedTags, $tag);
    }
    $validatedTags = array_unique($validatedTags);
    return $validatedTags;
}

function removeAllowedSpecialCharactersAtStatAndEndForLookupValueUpdate($tag)
{
    return removeAllowedSpecialCharactersAtStatAndEnd($tag);
}

function generateSlugForAddLookupValueUpdate($tag)
{
    return generateSlug($tag);
}

function cleanStringForAddLookupValue($tags, $dictionary)
{
    $validatedTags = [];
    foreach ($tags as $tag) {
//        $tag = trim($tag, "&");
        $tag = cleanStringTitleWithDictionary($tag, $dictionary);
        array_push($validatedTags, $tag);
    }
    $validatedTags = array_unique($validatedTags);
    return $validatedTags;
}

function generateSlugForAddLookupValue($tags)
{
    $validatedTags = [];
    foreach ($tags as $tag) {
        $tag = generateSlug($tag);
        array_push($validatedTags, $tag);
    }
    return $validatedTags;
}

function removeAllowedSpecialCharactersAtStatAndEnd($value)
{
    $remove = ",&-/\t\n\x0B ";
    $value = cleanString($value);
    return trim($value, $remove);
}

function generateVerificationCode($length = 4)
{
    if ($length === 4) {
        return mt_rand(1000, 9999);
    }
    return mt_rand();
}
