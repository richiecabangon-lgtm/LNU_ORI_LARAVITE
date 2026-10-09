<?php

namespace App\Enums;

enum EvaluationRecommendation: string
{
    case RecommendApproval = 'recommend_approval';
    case RecommendRejection = 'recommend_rejection';
    case RequestRevision = 'request_revision';

    public function label(): string
    {
        return match ($this) {
            self::RecommendApproval => 'Recommend for Approval',
            self::RecommendRejection => 'Recommend for Rejection',
            self::RequestRevision => 'Request Revision',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $item) => ['value' => $item->value, 'label' => $item->label()],
            self::cases(),
        );
    }
}
