export type RecipeType = 'menu_item' | 'prepared_item' | 'batch';

export type RecipeData = {
    id: number;
    code: string;
    name: string;
    type: RecipeType;
    active: boolean;
};
