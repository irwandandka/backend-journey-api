declare namespace App {
namespace Data {
export type AnswerResultData = {
position: number,
isCorrect: boolean,
correctOptionIds: number[],
explanation: string | null,
optionExplanations: Record<number, string>,
xpAwarded: number,
answeredCount: number,
questionCount: number,
};
export type AttemptData = {
id: number,
kind: string,
quizSlug: string | null,
status: string,
locale: string,
questionCount: number,
answeredCount: number,
startedAt: string,
};
export type AttemptResultData = {
score: number,
correctCount: number,
questionCount: number,
passed: boolean,
xpEarned: number,
xpTotal: number,
currentStreak: number,
journeyStatus: string | null,
scheduledForReview: {
position: number,
stem: string,
dueAt: string,
}[],
};
export type JourneyData = {
slug: string,
title: string,
description: string | null,
tier: string,
position: number,
status: string,
bestScore: number | null,
attemptsCount: number,
passThreshold: number,
quizSlug: string | null,
questionCount: number,
};
export type LoginData = {
email: string,
password: string,
remember: boolean,
};
export type RegisterData = {
name: string,
email: string,
password: string,
locale: string | null,
};
export type ReviewSummaryData = {
dueCount: number,
trackedCount: number,
nextDueAt: string | null,
sessionSize: number,
};
export type RoadmapData = {
slug: string,
title: string,
description: string | null,
tracks: App.Data.TrackSummaryData[],
};
export type ServedQuestionData = {
position: number,
stem: string,
codeSnippet: string | null,
codeLanguage: string | null,
answered: boolean,
options: {
id: number,
label: string,
}[],
};
export type SubmitAnswerData = {
position: number,
optionIds: number[],
timeSpentMs: number | null,
};
export type TrackDetailData = {
slug: string,
name: string,
description: string | null,
abbreviation: string,
colorDark: string,
colorLight: string,
journeys: App.Data.JourneyData[],
};
export type TrackSummaryData = {
slug: string,
name: string,
description: string | null,
abbreviation: string,
colorDark: string,
colorLight: string,
position: number,
journeyCount: number,
completedCount: number,
dueCount: number,
};
export type UserData = {
id: number,
name: string,
email: string,
locale: string,
xpTotal: number,
currentStreak: number,
longestStreak: number,
lastActiveOn: string | null,
roles: string[],
};
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}
