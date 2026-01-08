/// <reference lib="dom" />

export type Ean = number;

export type Livre = {
    i: Ean;  // ean
    t: string;  // titre
    d: Date | undefined;  // parution
    w?: number;  // weeks
    a?: string;  // auteur
    e?: string;  // editeur
    c?: string;  // collection
    p: number;  // prix
    r: Code;  // rayon
    n: number;  // n
    resume: string; // resume
}

export type Code = number | 'all';

export type Rayon = {
    code: Code;
    titre: string;
    label: string;
    slug: string;
    i: number;
    rayons: Rayon[];
    sousRayons: Rayons;
    parent: Rayon;
}

export type Rayons = Record<string, Rayon>;

export type Order = 't' | 'a' | 'd' | 'p';

export type Coeur = {
    slug: string;
    i: Ean;
    d: Date;
    t?: string;
}

export type LivrePanier = {
    ean: Ean;
    titre: string;
    auteur: string;
    rayon: Code;
    prix: number;
    quantity: number;
}

export type ElemEvent = {
    target?: HTMLElement;
}

export type HTMLElemEvent = Omit<Event, 'target'> & { target: (EventTarget & HTMLElement ) | null};

export type EventHandler<T, E extends Event> = import('solid-js/types/jsx.d.ts').JSX.EventHandler<T, E>;

export type EventT<T> = Event & { currentTarget: T; target: Element };

// https://github.com/standard-schema/standard-schema

/** The Standard Schema interface. */
export interface StandardSchemaV1<Input = unknown, Output = Input> {
  /** The Standard Schema properties. */
  readonly '~standard': StandardSchemaV1.Props<Input, Output>;
}

export declare namespace StandardSchemaV1 {
  /** The Standard Schema properties interface. */
  export interface Props<Input = unknown, Output = Input> {
    /** The version number of the standard. */
    readonly version: 1;
    /** The vendor name of the schema library. */
    readonly vendor: string;
    /** Validates unknown input values. */
    readonly validate: (
      value: unknown
    ) => Result<Output> | Promise<Result<Output>>;
    /** Inferred types associated with the schema. */
    readonly types?: Types<Input, Output> | undefined;
  }

  /** The result interface of the validate function. */
  export type Result<Output> = SuccessResult<Output> | FailureResult;

  /** The result interface if validation succeeds. */
  export interface SuccessResult<Output> {
    /** The typed output value. */
    readonly value: Output;
    /** The non-existent issues. */
    readonly issues?: undefined;
  }

  /** The result interface if validation fails. */
  export interface FailureResult {
    /** The issues of failed validation. */
    readonly issues: ReadonlyArray<Issue>;
  }

  /** The issue interface of the failure output. */
  export interface Issue {
    /** The error message of the issue. */
    readonly message: string;
    /** The path of the issue, if any. */
    readonly path?: ReadonlyArray<PropertyKey | PathSegment> | undefined;
  }

  /** The path segment interface of the issue. */
  export interface PathSegment {
    /** The key representing a path segment. */
    readonly key: PropertyKey;
  }

  /** The Standard Schema types interface. */
  export interface Types<Input = unknown, Output = Input> {
    /** The input type of the schema. */
    readonly input: Input;
    /** The output type of the schema. */
    readonly output: Output;
  }

  /** Infers the input type of a Standard Schema. */
  export type InferInput<Schema extends StandardSchemaV1> = NonNullable<
    Schema['~standard']['types']
  >['input'];

  /** Infers the output type of a Standard Schema. */
  export type InferOutput<Schema extends StandardSchemaV1> = NonNullable<
    Schema['~standard']['types']
  >['output'];
}