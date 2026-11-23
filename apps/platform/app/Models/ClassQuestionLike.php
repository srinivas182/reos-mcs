<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A classmate's like on a class question (A10.2b).
 *
 * @property int $id
 * @property int $class_question_id
 * @property int $user_id
 */
final class ClassQuestionLike extends Model
{
    public $timestamps = false;

    protected $fillable = ['class_question_id', 'user_id'];
}
