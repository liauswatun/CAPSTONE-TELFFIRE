<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\FaqItem;
use Illuminate\Database\Seeder;

/**
 * Fills the missing English (EN) translations for the current articles
 * dataset and any FAQ items that lack one.
 *
 * Translations live in database/seeders/data/article_english_*.php and are
 * keyed by article id; rows that no longer exist are skipped safely.
 */
class ArticleEnglishSeeder extends Seeder
{
    public function run(): void
    {
        $data = [];

        foreach (glob(__DIR__.'/data/article_english_*.php') as $file) {
            $data = array_replace($data, require $file);
        }

        foreach ($data as $id => $en) {
            $article = Article::find($id);

            if (! $article) {
                continue;
            }

            $article->update(array_filter([
                'title_en' => $en['title'] ?? null,
                'excerpt_en' => $en['excerpt'] ?? null,
                'content_en' => $en['content'] ?? null,
            ]));
        }

        $this->command?->info('Artikel: '.count($data).' terjemahan EN diterapkan.');

        $faq = FaqItem::find(7);

        if ($faq && ! filled($faq->question_en)) {
            $faq->update([
                'question_en' => 'What are the benefits of the TELF platform?',
                'answer_en' => '<p>The TELF platform helps users see where forest and land fire hotspots are located and explore data from the provincial to the village level, so it can be used for monitoring and decision-making.</p>',
            ]);
            $this->command?->info('FAQ #7: terjemahan EN diterapkan.');
        }
    }
}
