<?php

namespace App\Tests;

use App\Entity\Comment;
use App\SpamChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Test\InMemoryPlatform;

class SpamCheckerTest extends TestCase
{
    public function testSpamScoreWhenTheModelIsDown(): void
    {
        $comment = new Comment();
        $comment->setAuthor('Fabien');
        $comment->setEmail('fabien@example.com');
        $comment->setText('Such a nice conference!');

        $platform = new InMemoryPlatform(fn () => throw new RuntimeException('The model is down.'));
        $checker = new SpamChecker(new Agent($platform, 'gpt-5-mini'));

        $this->assertSame(1, $checker->getSpamScore($comment, []));
    }
    #[DataProvider('provideComments')]
    public function testSpamScore(int $expectedScore, string $answer): void
    {
        $comment = new Comment();
        $comment->setAuthor('Fabien');
        $comment->setEmail('fabien@example.com');
        $comment->setText('Such a nice conference!');

        $platform = new InMemoryPlatform($answer);
        $checker = new SpamChecker(new Agent($platform, 'gpt-5-mini'));

        $this->assertSame($expectedScore, $checker->getSpamScore($comment, []));
    }

    public static function provideComments(): iterable
    {
        yield 'blatant_spam' => [2, 'blatant spam'];
        yield 'maybe_spam' => [1, 'Maybe spam.'];
        yield 'ham' => [0, 'ham'];
    }
}
