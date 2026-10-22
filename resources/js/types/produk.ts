export interface Produk {
    id: number;
    sku: string;
    name: string;
    harga: number;
    stock: number;
    created_at: string;
    updated_at: string;
}

export interface ProdukInput {
    sku: string;
    name: string;
    harga: number;
    stock: number;
}

export interface StockAdjustment {
    adjustment: number;
}

export interface ApiResponse<T> {
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
}
