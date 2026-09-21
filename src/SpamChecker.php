<?php
namespace App;

use App\Entity\Comment;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

class SpamChecker
{
    public function __construct(
        private AgentInterface $agent,
    ) {
    }

    /**
     * @return int Spam score: 0: not spam, 1: maybe spam, 2: blatant spam
     */
    public function getSpamScore(Comment $comment, array $context): int
    {
        $messages = new MessageBag(
            Message::forSystem(<<<PROMPT
                You moderate comments submitted to a conference guestbook.
                Classify the comment as "ham", "maybe spam", or "blatant spam".
                Only answer with the classification.
                PROMPT),
            Message::ofUser(sprintf(<<<COMMENT
                IP: %s
                User agent: %s
                Author: %s (%s)
                Comment: %s
                COMMENT,
                $context['user_ip'] ?? '',
                $context['user_agent'] ?? '',
                $comment->getAuthor(),
                $comment->getEmail(),
                $comment->getText(),
            )),
        );

        try {
            $answer = strtolower($this->agent->call($messages)->asText());
        } catch (ExceptionInterface) {
            return 1;
        }

        return match (true) {
            str_contains($answer, 'blatant spam') => 2,
            str_contains($answer, 'maybe spam') => 1,
            default => 0,
        };

        return match (true) {
            str_contains($answer, 'blatant spam') => 2,
            str_contains($answer, 'maybe spam') => 1,
            default => 0,
        };
    }
}