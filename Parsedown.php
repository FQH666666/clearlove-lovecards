<?php
class Parsedown {
    public function text($text) {
        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
        
        $text = preg_replace('/^### (.*$)/m', '<h3 class="text-lg font-bold mb-2">$1</h3>', $text);
        $text = preg_replace('/^## (.*$)/m', '<h2 class="text-xl font-bold mb-3">$1</h2>', $text);
        $text = preg_replace('/^# (.*$)/m', '<h1 class="text-2xl font-bold mb-4">$1</h1>', $text);
        
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        
        $text = preg_replace('/`(.*?)`/', '<code class="bg-gray-100 px-2 py-1 rounded text-sm">$1</code>', $text);
        
        $text = preg_replace('/!\[(.*?)\]\((.*?)\)/', '<img src="$2" alt="$1" class="rounded-lg my-4 max-w-full">', $text);
        
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" class="text-blue-600 hover:underline">$1</a>', $text);
        
        $text = preg_replace('/^\> (.*$)/m', '<blockquote class="border-l-4 border-green-500 pl-4 my-3 text-gray-600 italic">$1</blockquote>', $text);
        
        $text = preg_replace('/^\- (.*$)/m', '<li class="ml-4">$1</li>', $text);
        $text = preg_replace('/^\d+\. (.*$)/m', '<li class="ml-4">$1</li>', $text);
        
        $text = preg_replace('/(\r?\n){2,}/', '</p><p class="mb-3">', $text);
        
        $text = '<p class="mb-3">' . $text . '</p>';
        
        $text = str_replace('<p class="mb-3"></p>', '', $text);
        
        return $text;
    }
}
