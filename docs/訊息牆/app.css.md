@tailwind base;
@tailwind components;
@tailwind utilities;

@layer components {
    /* 命名空間根容器 */
    .linkluv-comments { position: relative; }

    /* ── 骨架 (Vertical Spine) ── */
    /* 所有的子回復容器，畫出垂直主線 */
    .linkluv-comments .replies-inner {
        position: relative;
        margin-left: 20px;
        padding-left: 12px;
        border-left: 2px solid #d1d5db;
        /*這條線是貫穿所有回復的骨架 */
    }

    /* ── 樹枝 (L-Branch) ── */
    .linkluv-comments .reply-item {
        position: relative;
    }

    /* 畫出 L 型連接線 */
    .linkluv-comments .reply-item::before {
        content: "";
        position: absolute;
        left: -14px; /* 這裡對齊骨架 */
        top: 18px;   /* 垂直位置對齊頭像中間 */
        width: 14px;
        height: 2px;
        background: #d1d5db;
    }

    /* ── 結尾處理 (切斷多餘的線) ── */
    /* 當該層級是最後一個項目時，用白色塊蓋住多餘的垂直線 */
    .linkluv-comments .reply-item:last-child::after {
        content: "";
        position: absolute;
        left: -16px;
        top: 20px;
        bottom: 0;
        width: 6px;
        background: white; /* 這裡必須與背景色一致 */
    }    
}    